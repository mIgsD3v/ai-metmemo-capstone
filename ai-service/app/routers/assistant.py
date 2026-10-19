from fastapi import APIRouter, HTTPException, status

from app.schemas import AssistantRequest, AssistantResponse

router = APIRouter(prefix="/assistant", tags=["assistant"])


@router.post("/chat", response_model=AssistantResponse)
async def assistant_chat(request: AssistantRequest) -> AssistantResponse:
    """Owner's follow-up assistant: plans and calls contact tools, drafts messages."""
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED, "Follow-up assistant not implemented yet")
