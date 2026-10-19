import logging
import time
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, UploadFile, status
from openai import OpenAIError

from app import card_reader
from app.config import Settings, get_settings
from app.schemas import ScanResponse

router = APIRouter(prefix="/scan", tags=["scanner"])
logger = logging.getLogger("ai-service.scanner")

ALLOWED_TYPES = {"image/jpeg", "image/png", "image/webp"}
MAX_BYTES = 5 * 1024 * 1024


@router.post("", response_model=ScanResponse)
async def scan_card(image: UploadFile, settings: Annotated[Settings, Depends(get_settings)]) -> ScanResponse:
    """Read a business card photo into fields, flagging low-confidence ones for review."""
    if not settings.openai_api_key:
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "Card scanner is not configured")

    if image.content_type not in ALLOWED_TYPES:
        raise HTTPException(status.HTTP_415_UNSUPPORTED_MEDIA_TYPE, "Upload a JPG, PNG or WebP photo")

    data = await image.read()
    if not data:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, "The photo is empty")
    if len(data) > MAX_BYTES:
        raise HTTPException(status.HTTP_413_REQUEST_ENTITY_TOO_LARGE, "The photo is larger than 5 MB")

    started = time.perf_counter()
    try:
        result = await card_reader.read_card(data, image.content_type, settings)
    except (OpenAIError, ValueError) as error:
        logger.warning("scan failed: %s", type(error).__name__)
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, "The card could not be read") from error

    # Log timing and review flags only; never the card's contents (PII).
    flagged = [name for name, field in result if field.needs_review]
    logger.info("scan ok in %.0f ms, review=%s", (time.perf_counter() - started) * 1000, flagged)
    return result
