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


# Follow-up assistant (owner, tool calling)
class AssistantRequest(BaseModel):
    user_id: str
    message: str = Field(min_length=1, max_length=2000)
    history: list[ChatMessage] = []


class AssistantResponse(BaseModel):
    answer: str
    tool_calls: list[str] = []
