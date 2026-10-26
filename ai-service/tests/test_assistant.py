"""Follow-up assistant: the agent loop, its tools, and how it fails. The model's replies are scripted."""

import json

import httpx
import pytest
from fastapi.testclient import TestClient
from openai import APIConnectionError

from app.agent.llm import ModelReply, ToolCall
from app.agent.loop import GAVE_UP, MAX_STEPS, FollowUpAgent
from app.agent.tools import TOOL_DEFINITIONS, LaravelContactData, Toolbox, ToolError
from app.config import Settings, get_settings
from app.deps import get_chat_model, get_contact_data, get_embedder, get_notes_index_opener
from app.main import app
from app.notes_index import NotesIndex
from app.schemas import ChatMessage, NoteIn
from fakes import FakeEmbedder

pytestmark = pytest.mark.anyio  # async tests run on asyncio via anyio (ships with FastAPI)


@pytest.fixture
def anyio_backend():
    return "asyncio"


ALICE = "user-alice"
BOB = "user-bob"

JUAN = {"id": "c-juan", "full_name": "Juan Dela Cruz", "company": "Northpoint", "job_title": "IT Manager", "event": "Expo",
        "tags": [], "met_at": "2026-10-14", "next_follow_up_at": "2026-10-23", "days_overdue": 3, "last_contacted_at": None}
ISABEL = {**JUAN, "id": "c-isabel", "full_name": "Isabel Navarro", "days_overdue": 0, "next_follow_up_at": "2026-10-29"}
NOTES = {
    "c-juan": [
        {"id": "n-dues", "body": "Thinks the monthly dues of 6,800 are too high. Promised two cheaper options.", "created_at": "2026-10-21T10:00:00+08:00"},
        {"id": "n-budget", "body": "First-time buyer, 1BR in BGC, budget up to 6.5M.", "created_at": "2026-10-14T10:00:00+08:00"},
    ],
    "c-isabel": [],
}


class FakeContactData:
    """Alice's contacts, as Laravel's /internal API would return them."""

    def __init__(self, fail: bool = False):
        self.fail = fail
        self.requests = []

    def _check(self, user_id):
        self.requests.append(user_id)
        if self.fail:
            raise ToolError("The contacts database is unreachable right now.")
        if user_id != ALICE:
            return False
        return True

    async def followups(self, user_id, days):
        mine = self._check(user_id)
        return {"as_of": "2026-10-26", "days": days, "contacts": [JUAN] if mine else []}

    async def find_contacts(self, user_id, search):
        mine = self._check(user_id)
        found = [c for c in (JUAN, ISABEL) if search.lower() in c["full_name"].lower()]
        return {"contacts": found if mine else []}

    async def contact_notes(self, user_id, contact_id):
        mine = self._check(user_id)
        contact = {"c-juan": JUAN, "c-isabel": ISABEL}.get(contact_id)
        if not mine or contact is None:
            raise ToolError("There is no contact with that id.")
        return {"contact": contact, "notes": NOTES[contact_id]}


class ScriptedModel:
    """Replies in order. Records what it was sent, so tests can check the conversation."""

    def __init__(self, *replies):
        self.replies = list(replies)
        self.calls = []  # (messages, allow_tools)

    async def complete(self, messages, tools, allow_tools=True):
        self.calls.append(([dict(m) for m in messages], allow_tools))
        reply = self.replies.pop(0)
        return reply(messages) if callable(reply) else reply

    def tool_output(self, call_number=-1, index=-1):
        """The JSON a tool returned, as the model saw it on a given call."""
        tool_messages = [m for m in self.calls[call_number][0] if m["role"] == "tool"]
        return json.loads(tool_messages[index]["content"])


def call(tool, _id="call-1", **args):
    return ModelReply(content=None, tool_calls=[ToolCall(_id, tool, json.dumps(args))])


def say(text):
    return ModelReply(content=text)


@pytest.fixture
def index():
    notes_index = NotesIndex.connect(":memory:", dimensions=128)
    notes = [
        NoteIn(note_id="n-dues", user_id=ALICE, contact_id="c-juan", contact_name="Juan Dela Cruz",
               body=NOTES["c-juan"][0]["body"], created_at="2026-10-21T10:00:00+08:00"),
        NoteIn(note_id="n-bob", user_id=BOB, contact_id="c-bob", contact_name="Bob's Contact",
               body="Wants a pet-friendly unit with a dog park.", created_at="2026-10-20T10:00:00+08:00"),
        NoteIn(note_id="n-pet", user_id=ALICE, contact_id="c-kenji", contact_name="Kenji Watanabe",
               body="Needs a pet-friendly 3BR, has a small dog.", created_at="2026-10-22T10:00:00+08:00"),
    ]
    notes_index.upsert(notes, [FakeEmbedder.vector(n.body) for n in notes])
    return notes_index


def toolbox(index, data=None, user_id=ALICE):
    return Toolbox(user_id, data or FakeContactData(), lambda: index, FakeEmbedder())


async def run(model, tools, message="Who should I follow up with?", history=None):
    return await FollowUpAgent(model).run(tools, message, history)


# ---- The loop ------------------------------------------------------------------------------------

async def test_answers_directly_when_no_tool_is_needed(index):
    model = ScriptedModel(say("Hi! Ask me who to follow up with."))

    result = await run(model, toolbox(index), "Hello")

    assert result.answer == "Hi! Ask me who to follow up with."
    assert result.tool_calls == [] and result.steps == 1


async def test_calls_a_tool_then_answers_from_its_result(index):
    model = ScriptedModel(call("get_overdue_followups", days=7), say("Follow up with Juan Dela Cruz (3 days overdue)."))

    result = await run(model, toolbox(index))

    assert result.tool_calls == ["get_overdue_followups"]
    assert result.steps == 2
    assert model.tool_output()["overdue"][0]["full_name"] == "Juan Dela Cruz"


async def test_followups_are_split_by_urgency(index):
    today = {**ISABEL, "id": "c-today", "full_name": "Due Today", "next_follow_up_at": "2026-10-26"}
    data = FakeContactData()
    data.followups = lambda user_id, days: _async({"as_of": "2026-10-26", "contacts": [JUAN, today, ISABEL]})
    model = ScriptedModel(call("get_overdue_followups"), say("Juan is overdue; one is due today."))

    await run(model, toolbox(index, data))

    out = model.tool_output()
    assert [c["full_name"] for c in out["overdue"]] == ["Juan Dela Cruz"]
    assert [c["full_name"] for c in out["due_today"]] == ["Due Today"]
    assert [c["full_name"] for c in out["upcoming"]] == ["Isabel Navarro"]


async def _async(value):
    return value


async def test_multi_step_plan_find_read_then_draft(index):
    model = ScriptedModel(
        call("find_contact", name="Juan"),
        call("get_contact_notes", contact_id="c-juan"),
        call("draft_followup_message", contact_id="c-juan", subject="Two options with lower dues",
             body="Hi Juan, as promised, here are two 1BR options in BGC with lower monthly dues.", sources=["N1"]),
        say("Here's a draft for Juan, based on his concern about dues [N1]."),
    )

    result = await run(model, toolbox(index), "Draft a follow-up to Juan")

    assert result.tool_calls == ["find_contact", "get_contact_notes", "draft_followup_message"]
    [draft] = result.drafts
    assert draft.contact_name == "Juan Dela Cruz" and draft.channel == "email" and draft.sources == ["N1"]
    assert [s.note_id for s in result.sources] == ["n-dues"]
    assert "NOT been sent" in model.tool_output()["status"]


async def test_conversation_history_is_sent_to_the_model(index):
    model = ScriptedModel(say("His budget is up to 6.5M."))
    history = [ChatMessage(role="user", content="Tell me about Juan"), ChatMessage(role="assistant", content="Juan is a first-time buyer.")]

    await run(model, toolbox(index), "What's his budget?", history)

    sent = model.calls[0][0]
    assert [m["role"] for m in sent] == ["system", "user", "assistant", "user"]
    assert sent[2]["content"] == "Juan is a first-time buyer."


async def test_stops_after_the_step_limit_with_tools_switched_off(index):
    looping = [call("find_contact", f"call-{i}", name="Juan") for i in range(MAX_STEPS - 1)]
    model = ScriptedModel(*looping, say("Juan Dela Cruz is the only Juan."))

    result = await run(model, toolbox(index), "Find Juan")

    assert result.steps == MAX_STEPS
    assert [allow for _, allow in model.calls] == [True] * (MAX_STEPS - 1) + [False]
    assert result.answer == "Juan Dela Cruz is the only Juan."


async def test_empty_final_answer_falls_back_to_a_clear_message(index):
    model = ScriptedModel(ModelReply(content=None))

    result = await run(model, toolbox(index))

    assert result.answer == GAVE_UP


async def test_owner_name_is_in_the_prompt_so_drafts_are_signed(index):
    model = ScriptedModel(say("Hi."))

    await FollowUpAgent(model).run(toolbox(index), "Hello", owner_name="Maria Santos")

    assert "Sign drafts as Maria Santos" in model.calls[0][0][0]["content"]


async def test_several_matching_contacts_tells_the_model_to_ask(index):
    model = ScriptedModel(call("find_contact", name="a"), say("Which one: Juan Dela Cruz or Isabel Navarro?"))

    await run(model, toolbox(index), "Draft a follow-up")

    assert len(model.tool_output()["contacts"]) == 2
    assert model.tool_output()["message"].startswith("Several contacts match")


async def test_notes_can_be_read_by_name_in_one_step(index):
    model = ScriptedModel(call("get_contact_notes", name="Juan Dela"), say("Juan worries about the dues [N1]."))

    result = await run(model, toolbox(index), "What about Juan Dela Cruz?")

    assert model.tool_output()["contact"]["full_name"] == "Juan Dela Cruz"
    assert result.steps == 2 and result.sources[0].note_id == "n-dues"


async def test_notes_by_an_ambiguous_name_returns_the_choices(index):
    model = ScriptedModel(call("get_contact_notes", name="a"), say("Which one?"))

    await run(model, toolbox(index))

    assert "notes" not in model.tool_output()
    assert model.tool_output()["message"].startswith("Several contacts match")


# ---- Citations (grounding) -----------------------------------------------------------------------

async def test_citations_map_to_the_notes_and_invented_ones_are_dropped(index):
    model = ScriptedModel(
        call("search_notes", query="pet-friendly dog"),
        say("Kenji Watanabe needs a pet-friendly unit [N1]. He also wants a pool [N7]."),
    )

    result = await run(model, toolbox(index), "Who has a dog?")

    assert "[N7]" not in result.answer and "[N1]" in result.answer
    assert result.dropped_citations == ["N7"]
    assert [s.contact_name for s in result.sources] == ["Kenji Watanabe"]


async def test_draft_cannot_cite_notes_it_never_read(index):
    model = ScriptedModel(
        call("draft_followup_message", contact_id="c-juan", body="Hi Juan!", sources=["N3"]),
        say("I couldn't ground that draft."),
    )

    result = await run(model, toolbox(index))

    assert result.drafts == []
    assert "Unknown note refs" in model.tool_output()["error"]


async def test_draft_cannot_cite_another_contacts_note(index):
    model = ScriptedModel(
        call("search_notes", query="pet-friendly dog"),  # N1 is Kenji's note
        call("draft_followup_message", "call-2", contact_id="c-juan", body="Hi Juan, about your dog...", sources=["N1"]),
        say("Sorry."),
    )

    result = await run(model, toolbox(index))

    assert result.drafts == []
    assert "different contact" in model.tool_output()["error"]


async def test_an_email_draft_always_has_a_subject(index):
    model = ScriptedModel(call("draft_followup_message", contact_id="c-juan", body="Hi Juan!"), say("Drafted."))

    result = await run(model, toolbox(index))

    assert result.drafts[0].subject == "Following up, Juan"


# ---- Privacy -------------------------------------------------------------------------------------

def test_the_model_cannot_choose_whose_data_to_read():
    for tool in TOOL_DEFINITIONS:
        assert "user_id" not in json.dumps(tool["function"]["parameters"]), tool["function"]["name"]


async def test_search_only_ever_returns_the_owners_notes(index):
    model = ScriptedModel(call("search_notes", query="pet-friendly unit dog park", limit=10), say("Done."))

    await run(model, toolbox(index))

    names = {note["contact_name"] for note in model.tool_output()["notes"]}
    assert "Bob's Contact" not in names and "Kenji Watanabe" in names


async def test_every_contact_lookup_uses_the_request_user(index):
    data = FakeContactData()
    model = ScriptedModel(call("get_overdue_followups"), call("find_contact", "call-2", name="Juan"), say("Done."))

    await run(model, toolbox(index, data))

    assert data.requests and set(data.requests) == {ALICE}


# ---- Graceful failure ----------------------------------------------------------------------------

async def test_a_contact_with_no_notes_is_reported_not_invented(index):
    model = ScriptedModel(call("get_contact_notes", contact_id="c-isabel"), say("The notes don't say anything about Isabel yet."))

    await run(model, toolbox(index))

    assert model.tool_output()["notes"] == []
    assert model.tool_output()["message"].startswith("This contact has no notes yet (this is not an error)")


async def test_unknown_contact_is_an_error_message_not_a_crash(index):
    model = ScriptedModel(call("get_contact_notes", contact_id="c-nobody"), say("I couldn't find that contact."))

    result = await run(model, toolbox(index))

    assert model.tool_output() == {"error": "There is no contact with that id."}
    assert result.answer == "I couldn't find that contact."


async def test_contacts_database_down_is_reported_to_the_model(index):
    model = ScriptedModel(call("get_overdue_followups"), say("I can't reach your contacts right now."))

    result = await run(model, toolbox(index, FakeContactData(fail=True)))

    assert model.tool_output() == {"error": "The contacts database is unreachable right now."}
    assert result.tool_calls == ["get_overdue_followups"]


async def test_vector_store_down_only_disables_search(index):
    def broken_index():
        raise ConnectionError("qdrant is down")

    tools = Toolbox(ALICE, FakeContactData(), broken_index, FakeEmbedder())
    model = ScriptedModel(
        call("search_notes", query="dog"),
        call("get_overdue_followups", "call-2"),
        say("Search is down, but Juan is overdue."),
    )

    await run(model, tools)

    assert model.tool_output(index=0) == {"error": "Notes search is unavailable right now."}
    assert model.tool_output(index=1)["overdue"][0]["full_name"] == "Juan Dela Cruz"


@pytest.mark.parametrize(
    ("name", "arguments", "expected"),
    [
        ("send_email", "{}", "There is no tool called send_email."),
        ("find_contact", "{not json", "Invalid arguments for find_contact"),
        ("get_overdue_followups", '{"days": 500}', "Invalid arguments for get_overdue_followups"),
    ],
)
async def test_bad_tool_calls_come_back_as_errors(index, name, arguments, expected):
    model = ScriptedModel(ModelReply(None, [ToolCall("call-1", name, arguments)]), say("Sorry."))

    result = await run(model, toolbox(index))

    assert model.tool_output()["error"].startswith(expected)
    assert result.answer == "Sorry."


# ---- Laravel contact data client -----------------------------------------------------------------

async def test_laravel_client_sends_the_token_and_maps_errors():
    seen = []

    def handler(request: httpx.Request):
        seen.append(request)
        if request.url.path.endswith("/notes"):
            return httpx.Response(404)
        if "contacts" in request.url.path:
            return httpx.Response(500)
        return httpx.Response(200, json={"as_of": "2026-10-26", "contacts": []})

    data = LaravelContactData("http://laravel.test/", "secret", transport=httpx.MockTransport(handler))

    assert (await data.followups(ALICE, 7))["contacts"] == []
    assert str(seen[0].url) == "http://laravel.test/internal/users/user-alice/followups?days=7"
    assert seen[0].headers["X-Internal-Token"] == "secret"
    with pytest.raises(ToolError, match="no contact"):
        await data.contact_notes(ALICE, "c-x")
    with pytest.raises(ToolError, match="returned an error"):
        await data.find_contacts(ALICE, "juan")
    await data.aclose()


async def test_laravel_client_unreachable_is_a_tool_error():
    def handler(request):
        raise httpx.ConnectError("refused")

    data = LaravelContactData("http://laravel.test", "secret", transport=httpx.MockTransport(handler))
    with pytest.raises(ToolError, match="unreachable"):
        await data.followups(ALICE, 7)


# ---- HTTP endpoint -------------------------------------------------------------------------------

@pytest.fixture
def client(index):
    def use(model):
        app.dependency_overrides[get_chat_model] = lambda: model
        return TestClient(app, headers={"X-Internal-Token": "secret"})

    app.dependency_overrides[get_settings] = lambda: Settings(_env_file=None, internal_token="secret", openai_api_key="test")
    app.dependency_overrides[get_contact_data] = lambda: FakeContactData()
    app.dependency_overrides[get_notes_index_opener] = lambda: (lambda: index)
    app.dependency_overrides[get_embedder] = lambda: FakeEmbedder()
    yield use
    app.dependency_overrides.clear()


def test_chat_endpoint_returns_answer_tools_drafts_and_sources(client):
    model = ScriptedModel(
        call("get_contact_notes", contact_id="c-juan"),
        call("draft_followup_message", "call-2", contact_id="c-juan", channel="text", subject="ignored for texts",
             body="Hi Juan! Sending two options with lower dues today.", sources=["N1"]),
        say("Juan is worried about the dues [N1]. I drafted a text."),
    )

    response = client(model).post("/assistant/chat", json={"user_id": ALICE, "message": "Draft a text to Juan"})

    assert response.status_code == 200
    body = response.json()
    assert body["tool_calls"] == ["get_contact_notes", "draft_followup_message"]
    assert body["drafts"][0]["channel"] == "text" and body["drafts"][0]["subject"] is None
    assert body["sources"][0]["note_id"] == "n-dues"
    assert body["steps"] == 3


def test_model_outage_is_a_clear_502(client):
    class Down:
        async def complete(self, *args, **kwargs):
            raise APIConnectionError(request=httpx.Request("POST", "https://api.openai.com"))

    response = client(Down()).post("/assistant/chat", json={"user_id": ALICE, "message": "Who is due?"})

    assert response.status_code == 502
    assert response.json()["detail"] == "The assistant is unavailable right now"


def test_chat_rejects_empty_messages_and_long_histories(client):
    api = client(ScriptedModel())
    too_long = [{"role": "user", "content": "hi"}] * 21

    assert api.post("/assistant/chat", json={"user_id": ALICE, "message": ""}).status_code == 422
    assert api.post("/assistant/chat", json={"user_id": ALICE, "message": "hi", "history": too_long}).status_code == 422


def test_chat_without_an_api_key_reports_not_configured():
    app.dependency_overrides[get_settings] = lambda: Settings(_env_file=None, internal_token="", openai_api_key="")
    try:
        response = TestClient(app).post("/assistant/chat", json={"user_id": ALICE, "message": "Who is due?"})
        assert response.status_code == 503
    finally:
        app.dependency_overrides.clear()
