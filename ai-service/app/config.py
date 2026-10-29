from functools import lru_cache
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    app_env: str = "local"

    # Shared secret: Laravel sends it as X-Internal-Token. Empty disables the check (local dev only).
    internal_token: str = ""

    openai_api_key: str = ""
    chat_model: str = "gpt-4o-mini"
    embedding_model: str = "text-embedding-3-small"

    # Vector store for the owner's meeting notes (RAG): the Qdrant server.
    qdrant_url: str = "http://localhost:6333"
    qdrant_api_key: str = ""
    # Must match embedding_model (text-embedding-3-small returns 1536 numbers per text).
    embedding_dimensions: int = 1536

    # The follow-up assistant reads contacts from Laravel's /internal API (same shared token, other direction).
    # Locally this is a second `php artisan serve` (port 8003): the dev server handles one request at a time,
    # and the main one is busy waiting for this service while the assistant answers.
    laravel_url: str = "http://127.0.0.1:8003"
    # System prompt variant: baseline | structured | grounded (see app/agent/prompts.py).
    assistant_prompt: Literal["baseline", "structured", "grounded"] = "grounded"

    # Caching (app/cache.py). Empty turns it off; the service works the same, only slower and dearer.
    redis_url: str = ""

    langfuse_public_key: str = ""
    langfuse_secret_key: str = ""
    langfuse_host: str = "https://cloud.langfuse.com"


@lru_cache
def get_settings() -> Settings:
    return Settings()
