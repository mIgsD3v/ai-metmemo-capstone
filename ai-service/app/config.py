from functools import lru_cache

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

    redis_url: str = "redis://localhost:6379/0"

    langfuse_public_key: str = ""
    langfuse_secret_key: str = ""
    langfuse_host: str = "https://cloud.langfuse.com"


@lru_cache
def get_settings() -> Settings:
    return Settings()
