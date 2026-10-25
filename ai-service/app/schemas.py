"""Request/response contracts between Laravel and the AI service."""

from pydantic import BaseModel, Field


class ChatMessage(BaseModel):
    role: str = Field(pattern="^(user|assistant)$")
    content: str = Field(max_length=2000)


# Card scanner
class ScannedField(BaseModel):
    value: str | None
    confidence: float = Field(ge=0, le=1)
    needs_review: bool


class ScanResponse(BaseModel):
    full_name: ScannedField
    email: ScannedField
    phone: ScannedField
    company: ScannedField
    job_title: ScannedField


# Notes index (RAG over the owner's meeting notes)
class NoteIn(BaseModel):
    note_id: str = Field(min_length=1, max_length=64)
    user_id: str = Field(min_length=1, max_length=64)
    contact_id: str = Field(min_length=1, max_length=64)
    contact_name: str = Field(max_length=200)
    body: str = Field(min_length=1, max_length=4000)
    created_at: str = Field(max_length=40)


class IndexNotesRequest(BaseModel):
    notes: list[NoteIn] = Field(min_length=1, max_length=100)


class ForgetNotesRequest(BaseModel):
    user_id: str = Field(min_length=1, max_length=64)
    contact_id: str | None = Field(default=None, max_length=64)
    note_id: str | None = Field(default=None, max_length=64)


class SearchNotesRequest(BaseModel):
    user_id: str = Field(min_length=1, max_length=64)
    query: str = Field(min_length=1, max_length=500)
    contact_id: str | None = Field(default=None, max_length=64)
    limit: int = Field(default=5, ge=1, le=20)


class NoteHit(BaseModel):
    note_id: str
    contact_id: str
    contact_name: str
    created_at: str
    body: str
    score: float


class SearchNotesResponse(BaseModel):
    hits: list[NoteHit]


# Follow-up assistant (owner, tool calling)
class AssistantRequest(BaseModel):
    user_id: str
    message: str = Field(min_length=1, max_length=2000)
    history: list[ChatMessage] = []


class AssistantResponse(BaseModel):
    answer: str
    tool_calls: list[str] = []
