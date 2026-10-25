from fastapi import Depends, FastAPI

from app.deps import verify_internal_token
from app.routers import assistant, health, notes, scanner

app = FastAPI(title="MetMemo AI Service", version="0.1.0")

# Health is open so Docker and Laravel can probe it; everything else needs the internal token.
app.include_router(health.router)

protected = [Depends(verify_internal_token)]
app.include_router(scanner.router, dependencies=protected)
app.include_router(notes.router, dependencies=protected)
app.include_router(assistant.router, dependencies=protected)
