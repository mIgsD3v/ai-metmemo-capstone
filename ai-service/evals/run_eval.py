"""Evaluate the assistant: RAG quality on the golden set, and agent behaviour on the scenarios.

    cd ai-service
    python -m evals.run_eval --user <demo user id> --label baseline
    python -m evals.run_eval --user <demo user id> --label improved --compare baseline

RAG (evals/golden_set.json, 20 questions):
  - Retrieval: the question goes straight to the notes index (top 5). Hit@5, recall@5 and MRR against the
    notes that hold the answer. No LLM involved, so this isolates the index.
  - End to end, scored with RAGAS: faithfulness, answer relevancy, context precision and context recall.
    The contexts are every note the agent's tools returned while answering.
Agent (evals/scenarios.json, 15 scenarios, 8 of them failure cases): tool-selection accuracy and task
completion, from the automatic checks described in that file.

Each question runs the same steps as POST /assistant/chat (input guardrails, agent, output guardrails), in
process and without the answer cache, against Laravel (LARAVEL_URL), Qdrant and OpenAI. Every case is one
Langfuse trace tagged eval:<suite>, run:<label> and case:<id>, with its scores attached. Writes
evals/results/<label>.json and docs/evidence/eval-<label>.md; with --compare, also docs/evidence/eval-comparison.md.
Costs roughly $0.10 per full run (gpt-4o-mini answers and judges).
"""

import argparse
import asyncio
import json
import re
import statistics
import time
from dataclasses import asdict, dataclass, field
from datetime import datetime
from pathlib import Path

from openai import AsyncOpenAI
from ragas.embeddings import embedding_factory
from ragas.llms import llm_factory
from ragas.metrics.collections import AnswerRelevancy, ContextPrecisionWithReference, ContextRecall, Faithfulness

from app import tracing
from app.agent.llm import OpenAIChatModel
from app.agent.loop import CITATION, FollowUpAgent
from app.agent.tools import LaravelContactData, Toolbox
from app.config import get_settings
from app.deps import _notes_index
from app.embeddings import OpenAIEmbedder
from app.guardrails import OpenAIModerator, check_input, check_output, mask_pii
from app.schemas import ChatMessage

HERE = Path(__file__).parent
EVIDENCE = HERE.parent.parent / "docs" / "evidence"
PHONE = re.compile(r"(\+63|\b09\d{2})[\s-]?\d{3}")
# gpt-4o-mini list prices (USD per 1M tokens) when this was written; check before quoting.
PRICE_IN, PRICE_OUT = 0.15, 0.60
RAGAS = ["faithfulness", "answer_relevancy", "context_precision", "context_recall"]
RETRIEVAL = ["hit_at_5", "recall_at_5", "mrr"]


@dataclass
class Answer:
    """What one question produced, as the Assistant page would have received it."""

    text: str
    tool_calls: list[str] = field(default_factory=list)
    drafts: list[dict] = field(default_factory=list)
    contexts: list[str] = field(default_factory=list)  # every note a tool returned
    blocked: bool = False
    steps: int = 0
    seconds: float = 0.0
    input_tokens: int = 0
    output_tokens: int = 0
    guardrails: list[str] = field(default_factory=list)


class Env:
    def __init__(self, user: str, owner: str):
        self.settings = settings = get_settings()
        self.user, self.owner = user, owner
        self.model = OpenAIChatModel(settings)
        self.moderator = OpenAIModerator(settings)
        self.embedder = OpenAIEmbedder(settings)  # uncached: measure the real thing
        self.index = _notes_index(settings.qdrant_url, settings.qdrant_api_key, settings.embedding_dimensions)
        client = AsyncOpenAI(api_key=settings.openai_api_key)
        judge = llm_factory(settings.chat_model, client=client)
        self.metrics = {
            "faithfulness": Faithfulness(llm=judge),
            "answer_relevancy": AnswerRelevancy(llm=judge, embeddings=embedding_factory("openai", model=settings.embedding_model, client=client)),
            "context_precision": ContextPrecisionWithReference(llm=judge),
            "context_recall": ContextRecall(llm=judge),
        }

    async def ask(self, message: str, history: list[dict] | None = None) -> Answer:
        """POST /assistant/chat without the cache: input guardrails, agent, output guardrails."""
        started = time.perf_counter()
        verdict = await check_input(message, self.moderator)
        if verdict.refusal:
            return Answer(verdict.refusal, blocked=True, guardrails=verdict.events, seconds=time.perf_counter() - started)
        data = LaravelContactData(self.settings.laravel_url, self.settings.internal_token)
        tools = Toolbox(self.user, data, lambda: self.index, self.embedder)
        turns = [ChatMessage(role=turn["role"], content=mask_pii(turn["content"])[0]) for turn in history or []]
        try:
            result = await FollowUpAgent(self.model, self.settings.assistant_prompt).run(tools, verdict.text, turns, self.owner)
        finally:
            await data.aclose()
        output = await check_output(result.answer, [draft.body for draft in result.drafts], self.moderator)
        return Answer(
            text=output.refusal or output.text,
            tool_calls=result.tool_calls,
            drafts=[{"contact_name": d.contact_name, "channel": d.channel, "body": d.body, "sources": d.sources} for d in result.drafts],
            # What the model saw: tools return each note with its contact's name, so the judge gets it too.
            contexts=[f"Note about {source.contact_name}: {source.body}" for source in tools.sources.values()],
            steps=result.steps,
            seconds=time.perf_counter() - started,
            input_tokens=result.input_tokens,
            output_tokens=result.output_tokens,
            guardrails=verdict.events + result.guardrails + output.events,
        )


# ---- RAG -------------------------------------------------------------------------------------------

def matches(expected: dict, contact_name: str, body: str) -> bool:
    return expected["contact"].lower() in contact_name.lower() and expected["contains"].lower() in body.lower()


async def retrieval(env: Env, item: dict) -> dict:
    """Search the index directly with the question; score the top 5 against the notes that hold the answer."""
    vector = (await env.embedder.embed([item["question"]]))[0]
    hits = await asyncio.to_thread(env.index.search, env.user, vector, 5)
    ranks = []
    for expected in item["contexts"]:
        rank = next((i for i, hit in enumerate(hits, 1) if matches(expected, hit.contact_name, hit.body)), None)
        ranks.append(rank)
    found = [rank for rank in ranks if rank]
    return {
        "hit_at_5": float(bool(found)),
        "recall_at_5": len(found) / len(ranks),
        "mrr": 1 / min(found) if found else 0.0,
        "top5": [hit.contact_name for hit in hits],
    }


async def ragas_scores(env: Env, item: dict, answer: Answer) -> dict:
    response = CITATION.sub("", answer.text).strip()  # [N1] markers are for the UI, not the judge
    contexts = answer.contexts or ["(no notes were retrieved)"]
    calls = {
        "faithfulness": env.metrics["faithfulness"].ascore(user_input=item["question"], response=response, retrieved_contexts=contexts),
        "answer_relevancy": env.metrics["answer_relevancy"].ascore(user_input=item["question"], response=response),
        "context_precision": env.metrics["context_precision"].ascore(user_input=item["question"], reference=item["reference"], retrieved_contexts=contexts),
        "context_recall": env.metrics["context_recall"].ascore(user_input=item["question"], retrieved_contexts=contexts, reference=item["reference"]),
    }
    results = await asyncio.gather(*calls.values(), return_exceptions=True)
    scores = {}
    for name, result in zip(calls, results):
        if isinstance(result, Exception):
            print(f"    {name} failed: {type(result).__name__}: {result}")
            scores[name] = None
        else:
            scores[name] = round(float(result.value), 4)
    return scores


async def run_rag(env: Env, label: str) -> list[dict]:
    items = json.loads((HERE / "golden_set.json").read_text(encoding="utf-8"))["questions"]
    rows = []
    for item in items:
        with tracing.span(f"eval:{item['id']}", input={"question": item["question"]}) as span:
            tracing.update_trace(name=f"eval-rag:{item['id']}", user_id=env.user, tags=tags("rag", label, item["id"], env))
            answer = await env.ask(item["question"])
            scores = await retrieval(env, item) | await ragas_scores(env, item, answer)
            span.update(output={"answer": answer.text, "tool_calls": answer.tool_calls})
            for name in RAGAS + RETRIEVAL:
                if scores.get(name) is not None:
                    tracing.score(name, scores[name])
        rows.append({"id": item["id"], "by": item["by"], "question": item["question"], **scores, "answer": asdict(answer)})
        print(f"  {item['id']} {item['by']:5} " + " ".join(f"{name[:9]}={fmt(scores[name])}" for name in RAGAS + RETRIEVAL) + f"  {answer.tool_calls}")
    return rows


# ---- Agent -----------------------------------------------------------------------------------------

def check(scenario: dict, answer: Answer) -> tuple[bool, list[str]]:
    """(right tool, reasons the task failed). An empty reasons list means the task was completed."""
    text = answer.text.lower()
    everything = " ".join([answer.text] + [draft["body"] for draft in answer.drafts]).lower()
    reasons = []
    called = set(answer.tool_calls)
    expected = set(scenario["tools"])
    tool_ok = not called if not expected else bool(called & expected)
    if not tool_ok:
        reasons.append(f"tools {answer.tool_calls or 'none'}")
    if scenario.get("blocked") and not answer.blocked:
        reasons.append("not blocked")
    for phrase in scenario.get("must_say", []):
        if not any(option.lower() in text for option in phrase.split("|")):
            reasons.append(f"missing '{phrase}'")
    for phrase in scenario.get("must_not_say", []):
        if phrase.lower() in everything:
            reasons.append(f"said '{phrase}'")
    if PHONE.search(answer.text):
        reasons.append("phone number in answer")
    if (name := scenario.get("draft_for")) and not any(name.lower() in d["contact_name"].lower() for d in answer.drafts):
        reasons.append(f"no draft for {name}")
    if scenario.get("draft_for") and any(not d["sources"] for d in answer.drafts):
        reasons.append("ungrounded draft")
    if scenario.get("no_draft") and answer.drafts:
        reasons.append("drafted when it shouldn't")
    return tool_ok, reasons


async def run_agent(env: Env, label: str) -> list[dict]:
    scenarios = json.loads((HERE / "scenarios.json").read_text(encoding="utf-8"))["scenarios"]
    rows = []
    for scenario in scenarios:
        with tracing.span(f"eval:{scenario['id']}", input={"message": scenario["message"]}) as span:
            tracing.update_trace(name=f"eval-agent:{scenario['id']}", user_id=env.user, tags=tags("agent", label, scenario["id"], env))
            answer = await env.ask(scenario["message"], scenario.get("history"))
            tool_ok, reasons = check(scenario, answer)
            span.update(output={"answer": answer.text, "tool_calls": answer.tool_calls}, metadata={"failed_checks": reasons})
            tracing.score("tool_selection", float(tool_ok))
            tracing.score("task_completed", float(not reasons), comment="; ".join(reasons) or None)
        rows.append({"id": scenario["id"], "kind": scenario["kind"], "tests": scenario["tests"], "message": scenario["message"],
                     "tool_ok": tool_ok, "passed": not reasons, "reasons": reasons, "answer": asdict(answer)})
        print(f"  {scenario['id']:22} {'PASS' if not reasons else 'FAIL'} {answer.seconds:5.1f}s {answer.tool_calls} {'; '.join(reasons)}")
    return rows


# ---- Summaries and reports -------------------------------------------------------------------------

def tags(suite: str, label: str, case: str, env: Env) -> list[str]:
    return [f"eval:{suite}", f"run:{label}", f"case:{case}", f"prompt:{env.settings.assistant_prompt}"]


def fmt(value) -> str:
    return "n/a" if value is None else f"{value:.2f}"


def mean(rows: list[dict], name: str) -> float | None:
    values = [row[name] for row in rows if row.get(name) is not None]
    return round(statistics.mean(values), 4) if values else None


def summarize(results: dict) -> dict:
    summary = {}
    if rag := results.get("rag"):
        summary["rag"] = {name: mean(rag, name) for name in RAGAS + RETRIEVAL}
        for by in ("name", "topic"):
            group = [row for row in rag if row["by"] == by]
            summary["rag"][f"recall_at_5_{by}"] = mean(group, "recall_at_5")
    if agent := results.get("agent"):
        answers = [row["answer"] for row in agent]
        summary["agent"] = {
            "scenarios": len(agent),
            "tool_selection": sum(row["tool_ok"] for row in agent),
            "task_completion": sum(row["passed"] for row in agent),
            "normal_passed": sum(row["passed"] for row in agent if row["kind"] == "normal"),
            "normal": sum(row["kind"] == "normal" for row in agent),
            "failure_passed": sum(row["passed"] for row in agent if row["kind"] == "failure"),
            "failure": sum(row["kind"] == "failure" for row in agent),
            "avg_steps": round(statistics.mean(a["steps"] for a in answers), 2),
            "avg_seconds": round(statistics.mean(a["seconds"] for a in answers), 2),
        }
    every = [row["answer"] for suite in ("rag", "agent") for row in results.get(suite, [])]
    tokens_in, tokens_out = sum(a["input_tokens"] for a in every), sum(a["output_tokens"] for a in every)
    summary["cost"] = {"input_tokens": tokens_in, "output_tokens": tokens_out,
                       "assistant_usd": round(tokens_in / 1e6 * PRICE_IN + tokens_out / 1e6 * PRICE_OUT, 4)}
    return summary


def report(results: dict) -> str:
    s = results["summary"]
    lines = [
        f"# Evaluation: `{results['label']}`",
        "",
        f"Run {results['run_at']}, assistant `{results['model']}` with prompt `{results['prompt']}`, temperature 0, seeded demo",
        "account. RAGAS judge: the same model. Reproduce with `python -m evals.run_eval` (see the script's docstring);",
        "datasets: `ai-service/evals/golden_set.json` and `ai-service/evals/scenarios.json`. Every case is a Langfuse",
        f"trace tagged `run:{results['label']}` with these scores attached.",
        "",
    ]
    if rag := s.get("rag"):
        lines += [
            "## RAG: golden set (20 questions)",
            "",
            "| Metric | Score | What it measures |",
            "|---|---|---|",
            f"| Faithfulness (RAGAS) | {fmt(rag['faithfulness'])} | Answer claims supported by the retrieved notes |",
            f"| Answer relevancy (RAGAS) | {fmt(rag['answer_relevancy'])} | Answer addresses the question (not evasive) |",
            f"| Context precision (RAGAS) | {fmt(rag['context_precision'])} | Retrieved notes are useful, ranked first |",
            f"| Context recall (RAGAS) | {fmt(rag['context_recall'])} | Reference answer is covered by the retrieved notes |",
            f"| Retrieval hit@5 | {fmt(rag['hit_at_5'])} | Index search alone finds an answer note in the top 5 |",
            f"| Retrieval recall@5 | {fmt(rag['recall_at_5'])} (named: {fmt(rag['recall_at_5_name'])}, topic: {fmt(rag['recall_at_5_topic'])}) | Share of answer notes in the top 5 |",
            f"| Retrieval MRR | {fmt(rag['mrr'])} | 1 / rank of the first answer note |",
            "",
            "| Q | By | Question | Faith. | Relev. | Ctx prec. | Ctx recall | Hit@5 | Tools |",
            "|---|---|---|---|---|---|---|---|---|",
        ]
        for row in results["rag"]:
            lines.append(f"| {row['id']} | {row['by']} | {row['question']} | {fmt(row['faithfulness'])} | {fmt(row['answer_relevancy'])} | "
                         f"{fmt(row['context_precision'])} | {fmt(row['context_recall'])} | {'✅' if row['hit_at_5'] else '❌'} | "
                         f"{', '.join(row['answer']['tool_calls']) or '-'} |")
        lines.append("")
    if agent := s.get("agent"):
        lines += [
            "## Agent: scenarios (15)",
            "",
            "| Metric | Result |",
            "|---|---|",
            f"| Tool-selection accuracy | {agent['tool_selection']}/{agent['scenarios']} ({agent['tool_selection'] / agent['scenarios']:.0%}) |",
            f"| Task completion | {agent['task_completion']}/{agent['scenarios']} ({agent['task_completion'] / agent['scenarios']:.0%}) |",
            f"| Normal tasks completed | {agent['normal_passed']}/{agent['normal']} |",
            f"| Failure cases handled | {agent['failure_passed']}/{agent['failure']} |",
            f"| Avg steps / latency | {agent['avg_steps']} / {agent['avg_seconds']} s |",
            "",
            "| Scenario | Kind | Tests | Result | Tools |",
            "|---|---|---|---|---|",
        ]
        for row in results["agent"]:
            result = "✅" if row["passed"] else "❌ " + "; ".join(row["reasons"])
            lines.append(f"| {row['id']} | {row['kind']} | {row['tests']} | {result} | {', '.join(row['answer']['tool_calls']) or '-'} |")
        lines.append("")
    cost = s["cost"]
    lines += [
        f"Assistant tokens for the whole run: {cost['input_tokens']:,} in / {cost['output_tokens']:,} out "
        f"(about ${cost['assistant_usd']:.3f} at list price; RAGAS judging is extra).",
        "",
        "## Answers",
        "",
    ]
    for suite in ("rag", "agent"):
        for row in results.get(suite, []):
            a = row["answer"]
            drafts = "".join(f" — draft to {d['contact_name']} ({d['channel']})" for d in a["drafts"])
            lines.append(f"- **{row['id']}** `{a['tool_calls']}`{' (blocked)' if a['blocked'] else ''}: {a['text'].replace(chr(10), ' ')}{drafts}")
    return "\n".join(lines) + "\n"


def comparison(before: dict, after: dict) -> str:
    b, a = before["summary"], after["summary"]
    lines = [
        f"# Evaluation: `{before['label']}` vs `{after['label']}`",
        "",
        f"Before: {before['run_at']}. After: {after['run_at']}. Same datasets, model and judge; see",
        f"`eval-{before['label']}.md` and `eval-{after['label']}.md` for every answer.",
        "",
        "| Metric | Before | After | Change |",
        "|---|---|---|---|",
    ]

    def row(name, x, y, as_count=None):
        if x is None or y is None:
            return f"| {name} | {fmt(x)} | {fmt(y)} | |"
        if as_count:
            return f"| {name} | {x}/{as_count} | {y}/{as_count} | {y - x:+d} |"
        return f"| {name} | {x:.2f} | {y:.2f} | {y - x:+.2f} |"

    if "rag" in b and "rag" in a:
        for name, label in [("faithfulness", "Faithfulness"), ("answer_relevancy", "Answer relevancy"), ("context_precision", "Context precision"),
                            ("context_recall", "Context recall"), ("hit_at_5", "Retrieval hit@5"), ("recall_at_5", "Retrieval recall@5"),
                            ("recall_at_5_name", "  … named questions"), ("recall_at_5_topic", "  … topic questions"), ("mrr", "Retrieval MRR")]:
            lines.append(row(label, b["rag"][name], a["rag"][name]))
    if "agent" in b and "agent" in a:
        n = a["agent"]["scenarios"]
        lines.append(row("Tool-selection accuracy", b["agent"]["tool_selection"], a["agent"]["tool_selection"], n))
        lines.append(row("Task completion", b["agent"]["task_completion"], a["agent"]["task_completion"], n))
        lines.append(row("Failure cases handled", b["agent"]["failure_passed"], a["agent"]["failure_passed"], a["agent"]["failure"]))
    changed = []
    for suite, key in (("agent", "passed"), ("rag", "hit_at_5")):
        old = {row["id"]: row for row in before.get(suite, [])}
        for row in after.get(suite, []):
            if row["id"] in old and bool(old[row["id"]][key]) != bool(row[key]):
                changed.append(f"- **{row['id']}**: {'❌ → ✅' if row[key] else '✅ → ❌'} {row.get('tests') or row['question']}")
    lines += ["", "## Cases that changed", ""] + (changed or ["- None."])
    return "\n".join(lines) + "\n"


async def main(args) -> None:
    env = Env(args.user, args.owner)
    tracing.configure(env.settings)
    results = {"label": args.label, "run_at": f"{datetime.now():%Y-%m-%d %H:%M}", "model": env.settings.chat_model,
               "prompt": env.settings.assistant_prompt}
    if args.suite in ("rag", "all"):
        print("RAG golden set")
        results["rag"] = await run_rag(env, args.label)
    if args.suite in ("agent", "all"):
        print("Agent scenarios")
        results["agent"] = await run_agent(env, args.label)
    tracing.flush()
    results["summary"] = summarize(results)

    out = HERE / "results" / f"{args.label}.json"
    out.parent.mkdir(exist_ok=True)
    out.write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding="utf-8")
    (EVIDENCE / f"eval-{args.label}.md").write_text(report(results), encoding="utf-8")
    print(f"\n{json.dumps(results['summary'], indent=2)}\nWrote {out} and docs/evidence/eval-{args.label}.md")
    if args.compare:
        before = json.loads((HERE / "results" / f"{args.compare}.json").read_text(encoding="utf-8"))
        (EVIDENCE / "eval-comparison.md").write_text(comparison(before, results), encoding="utf-8")
        print("Wrote docs/evidence/eval-comparison.md")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--user", required=True, help="The demo account's user id")
    parser.add_argument("--owner", default="Maria Santos", help="Owner name drafts are signed with")
    parser.add_argument("--label", required=True, help="Name of this run, e.g. baseline or improved")
    parser.add_argument("--suite", choices=["rag", "agent", "all"], default="all")
    parser.add_argument("--compare", help="Label of an earlier run to compare against")
    asyncio.run(main(parser.parse_args()))
