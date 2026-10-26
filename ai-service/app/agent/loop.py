"""The agent loop: the model plans, calls tools, reads the results, and repeats until it can answer.

Bounded to MAX_STEPS model calls. On the last step the tools are switched off, so the model has to
answer with what it has gathered instead of looping forever.
"""

import json
import re
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone

from app.agent.llm import ChatModel
from app.agent.prompts import system_prompt
from app.agent.tools import Toolbox
from app.schemas import ChatMessage, Draft, Source

MAX_STEPS = 5
MANILA = timezone(timedelta(hours=8))  # the Philippines has no daylight saving time
CITATION = re.compile(r"\[(N\d+)\]")

GAVE_UP = "Sorry, I couldn't finish that within my step limit. Try asking about one contact or one thing at a time."


@dataclass
class AgentResult:
    answer: str
    tool_calls: list[str] = field(default_factory=list)
    drafts: list[Draft] = field(default_factory=list)
    sources: list[Source] = field(default_factory=list)
    steps: int = 0
    dropped_citations: list[str] = field(default_factory=list)  # refs the model invented (grounding check)
    guardrails: list[str] = field(default_factory=list)  # guardrail events fired while answering


class FollowUpAgent:
    def __init__(self, model: ChatModel, prompt_variant: str = "grounded", max_steps: int = MAX_STEPS):
        self._model = model
        self._prompt_variant = prompt_variant
        self._max_steps = max_steps

    async def run(
        self, tools: Toolbox, message: str, history: list[ChatMessage] | None = None, owner_name: str | None = None
    ) -> AgentResult:
        today = datetime.now(MANILA).strftime("%A, %d %B %Y")
        prompt = system_prompt(self._prompt_variant, today, owner_name or "the user")
        messages: list[dict] = [{"role": "system", "content": prompt}]
        messages += [{"role": turn.role, "content": turn.content} for turn in history or []]
        messages.append({"role": "user", "content": message})

        called: list[str] = []
        for step in range(1, self._max_steps + 1):
            reply = await self._model.complete(messages, tools.definitions, allow_tools=step < self._max_steps)

            if not reply.tool_calls:
                return self._finish(tools, reply.content or GAVE_UP, called, step)

            messages.append(
                {
                    "role": "assistant",
                    "content": reply.content,
                    "tool_calls": [
                        {"id": call.id, "type": "function", "function": {"name": call.name, "arguments": call.arguments}}
                        for call in reply.tool_calls
                    ],
                }
            )
            for call in reply.tool_calls:
                called.append(call.name)
                output = await tools.call(call.name, call.arguments)
                messages.append({"role": "tool", "tool_call_id": call.id, "content": json.dumps(output, ensure_ascii=False)})

        # Only reached if the model ignored tool_choice="none" on the last step.
        return self._finish(tools, GAVE_UP, called, self._max_steps)

    @staticmethod
    def _finish(tools: Toolbox, answer: str, called: list[str], steps: int) -> AgentResult:
        # Keep citations that point at notes the tools really returned; drop invented ones.
        cited = CITATION.findall(answer)
        dropped = sorted({ref for ref in cited if ref not in tools.sources})
        for ref in dropped:
            answer = answer.replace(f"[{ref}]", "")

        used = [ref for ref in dict.fromkeys(cited) if ref in tools.sources]
        for draft in tools.drafts:
            used += [ref for ref in draft.sources if ref not in used]

        return AgentResult(
            answer=answer.strip(),
            tool_calls=called,
            drafts=tools.drafts,
            sources=[tools.sources[ref] for ref in used],
            steps=steps,
            dropped_citations=dropped,
            guardrails=tools.guardrails + ([f"output.invented_citation x{len(dropped)}"] if dropped else []),
        )
