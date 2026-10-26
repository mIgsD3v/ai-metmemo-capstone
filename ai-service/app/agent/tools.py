"""The assistant's tools, bound to one user for one request.

The owner's user_id is fixed when the Toolbox is built and is never a tool argument, so the model
cannot ask for another user's data, however it is prompted. Contacts and notes come from Laravel's
read-only /internal API (which owns the ownership rules); note search runs on the vector index.

A tool never raises into the agent loop: failures come back as {"error": "..."} for the model to
explain, so a broken tool degrades the answer instead of crashing the request.
"""

import asyncio
import json
import logging
from collections.abc import Callable
from typing import Literal, Protocol

import httpx
from pydantic import BaseModel, Field, ValidationError, model_validator

from app import tracing
from app.embeddings import Embedder
from app.guardrails import check_note
from app.notes_index import NotesIndex
from app.schemas import Draft, Source

logger = logging.getLogger("ai-service.assistant")

MAX_NOTES_PER_CONTACT = 20


class ToolError(Exception):
    """A failure the model can be told about. The message must be safe to show the user."""


# ---- Contact data (Laravel /internal API) ------------------------------------------------------

class ContactData(Protocol):
    async def followups(self, user_id: str, days: int) -> dict: ...
    async def find_contacts(self, user_id: str, search: str) -> dict: ...
    async def contact_notes(self, user_id: str, contact_id: str) -> dict: ...


class LaravelContactData:
    """Reads contacts through Laravel, authenticated with the shared internal token."""

    def __init__(self, base_url: str, token: str, timeout: float = 10, transport: httpx.AsyncBaseTransport | None = None):
        self._client = httpx.AsyncClient(
            base_url=f"{base_url.rstrip('/')}/internal",
            headers={"X-Internal-Token": token, "Accept": "application/json"},
            timeout=timeout,
            transport=transport,
        )

    async def aclose(self) -> None:
        await self._client.aclose()

    async def followups(self, user_id: str, days: int) -> dict:
        return await self._get(f"/users/{user_id}/followups", {"days": days})

    async def find_contacts(self, user_id: str, search: str) -> dict:
        return await self._get(f"/users/{user_id}/contacts", {"search": search})

    async def contact_notes(self, user_id: str, contact_id: str) -> dict:
        return await self._get(f"/users/{user_id}/contacts/{contact_id}/notes")

    async def _get(self, path: str, params: dict | None = None) -> dict:
        try:
            response = await self._client.get(path, params=params)
        except httpx.HTTPError as error:
            logger.warning("contacts API unreachable: %s", type(error).__name__)
            raise ToolError("The contacts database is unreachable right now.") from error
        if response.status_code == 404:
            raise ToolError("There is no contact with that id.")
        if response.status_code >= 400:
            logger.warning("contacts API returned %d", response.status_code)
            raise ToolError("The contacts database returned an error.")
        return response.json()


# ---- Tool arguments: validated, and also the JSON schemas the model sees -------------------------

class OverdueArgs(BaseModel):
    """Contacts whose follow-up is overdue or due within the next `days` days, most overdue first."""

    days: int = Field(default=7, ge=0, le=90, description="Look ahead this many days (0 = due today or overdue only).")


class FindContactArgs(BaseModel):
    """Find contacts by name, company or event. Use it to get a contact_id from a name."""

    name: str = Field(min_length=1, max_length=100, description="Part of a name, company or event.")


class ContactNotesArgs(BaseModel):
    """All meeting notes for one contact, newest first, each with a ref like N1 to cite.
    Pass contact_id if a tool gave you one; otherwise pass the person's name."""

    contact_id: str | None = Field(default=None, max_length=64)
    name: str | None = Field(default=None, max_length=100)

    @model_validator(mode="after")
    def one_of_id_or_name(self):
        if not self.contact_id and not self.name:
            raise ValueError("pass contact_id or name")
        return self


class SearchNotesArgs(BaseModel):
    """Search all of the user's meeting notes by meaning (not exact words). Use it when no name is given."""

    query: str = Field(min_length=1, max_length=300, description="What to look for, e.g. 'wants a pet-friendly unit'.")
    contact_id: str | None = Field(default=None, max_length=64, description="Only this contact's notes.")
    limit: int = Field(default=5, ge=1, le=10)


class DraftArgs(BaseModel):
    """Save a follow-up message for the user to review. It is NOT sent: the user sends it themselves."""

    contact_id: str = Field(min_length=1, max_length=64)
    body: str = Field(min_length=1, max_length=1500, description="The message only, in the user's voice, signed with their name. No subject line in it.")
    channel: Literal["email", "text"] = "email"
    subject: str | None = Field(default=None, max_length=150, description="Required for email: a short, specific subject line.")
    sources: list[str] = Field(default=[], max_length=10, description="Refs of the notes this draft is based on, e.g. ['N1'].")


TOOLS: dict[str, type[BaseModel]] = {
    "get_overdue_followups": OverdueArgs,
    "find_contact": FindContactArgs,
    "get_contact_notes": ContactNotesArgs,
    "search_notes": SearchNotesArgs,
    "draft_followup_message": DraftArgs,
}


def _schema(model: type[BaseModel]) -> dict:
    schema = model.model_json_schema()
    schema.pop("title", None)
    schema.pop("description", None)
    for prop in schema.get("properties", {}).values():
        prop.pop("title", None)
    return schema


TOOL_DEFINITIONS = [
    {"type": "function", "function": {"name": name, "description": model.__doc__, "parameters": _schema(model)}}
    for name, model in TOOLS.items()
]


# ---- The toolbox -------------------------------------------------------------------------------

class Toolbox:
    def __init__(self, user_id: str, data: ContactData, index: Callable[[], NotesIndex], embedder: Embedder):
        self._user_id = user_id
        self._data = data
        self._index = index  # opened lazily: if the vector store is down, only search_notes fails
        self._embedder = embedder
        self._contacts: dict[str, str] = {}  # contact ids this run has seen -> names
        self.sources: dict[str, Source] = {}  # ref -> note
        self._refs: dict[str, str] = {}  # note id -> ref
        self.drafts: list[Draft] = []
        self.guardrails: list[str] = []  # guardrail events fired on tool results (PII masked, injection flagged)

    definitions = TOOL_DEFINITIONS

    async def call(self, name: str, arguments: str) -> dict:
        with tracing.span(f"tool:{name}", input=arguments) as span:
            output = await self._call(name, arguments)
            span.update(output=output, level="WARNING" if "error" in output else "DEFAULT")
            return output

    async def _call(self, name: str, arguments: str) -> dict:
        model = TOOLS.get(name)
        if model is None:
            return {"error": f"There is no tool called {name}."}
        try:
            args = model.model_validate(json.loads(arguments or "{}"))
        except (json.JSONDecodeError, ValidationError) as error:
            return {"error": f"Invalid arguments for {name}: {error}"}
        try:
            return await getattr(self, name)(args)
        except ToolError as error:
            return {"error": str(error)}

    async def get_overdue_followups(self, args: OverdueArgs) -> dict:
        data = await self._data.followups(self._user_id, args.days)
        today = data.get("as_of")
        contacts = [self._contact(c) for c in data.get("contacts", [])]
        # Split by urgency: "today" questions must still surface overdue people first.
        return {
            "as_of": today,
            "overdue": [c for c in contacts if c.get("days_overdue", 0) > 0],
            "due_today": [c for c in contacts if not c.get("days_overdue") and c.get("next_follow_up_at") == today],
            "upcoming": [c for c in contacts if not c.get("days_overdue") and c.get("next_follow_up_at") != today],
            "note": "Overdue contacts are the most urgent; include them whenever the user asks who to follow up with.",
        }

    async def find_contact(self, args: FindContactArgs) -> dict:
        data = await self._data.find_contacts(self._user_id, args.name)
        contacts = [self._contact(c) for c in data.get("contacts", [])]
        if not contacts:
            return {"contacts": [], "message": f"No contact matches '{args.name}'."}
        if len(contacts) > 1:
            return {"contacts": contacts, "message": "Several contacts match. Unless the user already said which one, ask them before going on."}
        return {"contacts": contacts}

    async def get_contact_notes(self, args: ContactNotesArgs) -> dict:
        contact_id = args.contact_id
        if not contact_id:  # by name: only when it's unambiguous
            found = await self.find_contact(FindContactArgs(name=args.name))
            if len(found["contacts"]) != 1:
                return found
            contact_id = found["contacts"][0]["id"]
        data = await self._data.contact_notes(self._user_id, contact_id)
        contact = self._contact(data["contact"])
        notes = [
            self._note(note["id"], contact["id"], contact["full_name"], note["created_at"], note["body"])
            for note in data.get("notes", [])[:MAX_NOTES_PER_CONTACT]
        ]
        result = {"contact": contact, "notes": notes}
        if not notes:
            result["message"] = "This contact has no notes yet (this is not an error). Say the notes don't mention anything yet."
        return result

    async def search_notes(self, args: SearchNotesArgs) -> dict:
        try:
            [vector] = await self._embedder.embed([args.query])
            index = await asyncio.to_thread(self._index)
            hits = await asyncio.to_thread(index.search, self._user_id, vector, args.limit, args.contact_id)
        except Exception as error:  # embedding API or vector store down: search is optional, keep going
            logger.warning("search_notes failed: %s", type(error).__name__)
            raise ToolError("Notes search is unavailable right now.") from error
        for hit in hits:
            self._contacts.setdefault(hit.contact_id, hit.contact_name)
        return {
            "notes": [
                self._note(hit.note_id, hit.contact_id, hit.contact_name, hit.created_at, hit.body) | {"score": hit.score}
                for hit in hits
            ]
        }

    async def draft_followup_message(self, args: DraftArgs) -> dict:
        name = self._contacts.get(args.contact_id)
        if name is None:  # not seen in this run: check it exists and belongs to this user
            name = (await self._data.contact_notes(self._user_id, args.contact_id))["contact"]["full_name"]
            self._contacts[args.contact_id] = name

        # Grounding check: a draft may only cite notes this run actually read, about this contact.
        unknown = [ref for ref in args.sources if ref not in self.sources]
        if unknown:
            return {"error": f"Unknown note refs {unknown}. Cite only refs returned by the tools."}
        foreign = [ref for ref in args.sources if self.sources[ref].contact_id != args.contact_id]
        if foreign:
            return {"error": f"Notes {foreign} are about a different contact than {name}."}

        self.drafts.append(
            Draft(
                contact_id=args.contact_id,
                contact_name=name,
                channel=args.channel,
                subject=(args.subject or f"Following up, {name.split()[0]}") if args.channel == "email" else None,
                body=args.body,
                sources=args.sources,
            )
        )
        return {"draft": len(self.drafts), "contact_name": name, "status": "Saved for the user to review. It has NOT been sent."}

    def _contact(self, contact: dict) -> dict:
        self._contacts[contact["id"]] = contact["full_name"]
        return contact

    def _note(self, note_id: str, contact_id: str, contact_name: str, created_at: str, body: str) -> dict:
        # Guardrail: the model sees notes with contact details masked, and flagged if they look like instructions.
        body, warning, events = check_note(body)
        ref = self._refs.get(note_id)
        if ref is None:
            ref = f"N{len(self._refs) + 1}"
            self._refs[note_id] = ref
            self.sources[ref] = Source(
                ref=ref, note_id=note_id, contact_id=contact_id, contact_name=contact_name, created_at=created_at, body=body
            )
            self.guardrails += events
        note = {"ref": ref, "contact_id": contact_id, "contact_name": contact_name, "written": created_at[:10], "text": body}
        if warning:
            note["warning"] = warning
        return note
