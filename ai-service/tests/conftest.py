"""Test-wide setup. Runs before the app is imported."""

import os

# Real environment variables win over ai-service/.env, so tests never send traces to Langfuse
# or call OpenAI with the developer's keys.
os.environ["LANGFUSE_PUBLIC_KEY"] = ""
os.environ["LANGFUSE_SECRET_KEY"] = ""
os.environ["OPENAI_API_KEY"] = ""
