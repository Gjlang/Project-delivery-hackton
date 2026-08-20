"""
Lightweight observability for the project-creation graph: every LLM call's
token usage/latency, and every rule/employee check's outcome, get written to
a local SQLite file. dashboard.py (Gradio) reads from this same file to show
what's happening without needing LangSmith or any external service.
"""

import json
import sqlite3
import time
from contextlib import contextmanager
from pathlib import Path

from langchain_core.callbacks import BaseCallbackHandler

DB_PATH = Path(__file__).parent / "storage" / "tracking.db"
DB_PATH.parent.mkdir(parents=True, exist_ok=True)


@contextmanager
def _connect():
    conn = sqlite3.connect(DB_PATH, timeout=10)
    conn.row_factory = sqlite3.Row
    try:
        yield conn
        conn.commit()
    finally:
        conn.close()


def init_db() -> None:
    with _connect() as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS llm_calls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp TEXT NOT NULL DEFAULT (datetime('now')),
                thread_id TEXT,
                node TEXT NOT NULL,
                provider TEXT,
                model TEXT,
                input_tokens INTEGER DEFAULT 0,
                output_tokens INTEGER DEFAULT 0,
                total_tokens INTEGER DEFAULT 0,
                latency_ms INTEGER DEFAULT 0,
                status TEXT DEFAULT 'ok',
                error TEXT,
                raw_response TEXT,
                structured_output TEXT
            )
            """
        )
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS rule_checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp TEXT NOT NULL DEFAULT (datetime('now')),
                thread_id TEXT,
                rule_code TEXT NOT NULL,
                category TEXT,
                status TEXT NOT NULL,
                reason TEXT
            )
            """
        )
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS employee_checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp TEXT NOT NULL DEFAULT (datetime('now')),
                thread_id TEXT,
                employee_id INTEGER,
                employee_name TEXT,
                role TEXT,
                eligible INTEGER,
                reason TEXT,
                recommended INTEGER DEFAULT 0
            )
            """
        )

        # CREATE TABLE IF NOT EXISTS doesn't add columns to a table that
        # already exists on disk from before a schema change -- add any
        # missing ones so an older tracking.db self-heals instead of
        # erroring on every query that touches a new column.
        existing = {row["name"] for row in conn.execute("PRAGMA table_info(llm_calls)")}
        for column in ("raw_response", "structured_output"):
            if column not in existing:
                conn.execute(f"ALTER TABLE llm_calls ADD COLUMN {column} TEXT")


init_db()


def record_llm_call(
    node: str,
    provider: str | None,
    model: str | None,
    input_tokens: int,
    output_tokens: int,
    total_tokens: int,
    latency_ms: int,
    thread_id: str | None = None,
    status: str = "ok",
    error: str | None = None,
    raw_response: str | None = None,
) -> int:
    with _connect() as conn:
        cursor = conn.execute(
            """
            INSERT INTO llm_calls
                (thread_id, node, provider, model, input_tokens, output_tokens, total_tokens, latency_ms, status, error, raw_response)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (thread_id, node, provider, model, input_tokens, output_tokens, total_tokens, latency_ms, status, error, raw_response),
        )
        return cursor.lastrowid


def update_llm_structured_output(row_id: int, structured_output: str) -> None:
    """Filled in after record_llm_call() -- the parsed structured result is
    only available at the node's call site once with_structured_output()'s
    Runnable has finished parsing, which happens after the callback that
    writes the initial row."""
    with _connect() as conn:
        conn.execute(
            "UPDATE llm_calls SET structured_output = ? WHERE id = ?",
            (structured_output, row_id),
        )


def record_rule_check(rule_code: str, category: str | None, status: str, reason: str | None, thread_id: str | None = None) -> None:
    with _connect() as conn:
        conn.execute(
            "INSERT INTO rule_checks (thread_id, rule_code, category, status, reason) VALUES (?, ?, ?, ?, ?)",
            (thread_id, rule_code, category, status, reason),
        )


def record_employee_check(
    employee_id: int,
    employee_name: str,
    role: str,
    eligible: bool,
    reason: str | None,
    recommended: bool = False,
    thread_id: str | None = None,
) -> None:
    with _connect() as conn:
        conn.execute(
            """
            INSERT INTO employee_checks (thread_id, employee_id, employee_name, role, eligible, reason, recommended)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            """,
            (thread_id, employee_id, employee_name, role, int(eligible), reason, int(recommended)),
        )


class UsageTracker(BaseCallbackHandler):
    """
    Bound onto a specific LLM instance via .with_config({"callbacks": [...]})
    in llm.py -- since each binding in llm.py already corresponds to exactly
    one graph node (scope_llm -> scope_guard, etc.), the node name is fixed
    at construction time rather than pulled from ambient graph metadata.
    """

    def __init__(self, node: str):
        self.node = node
        self._pending: dict[str, float] = {}
        self.last_row_id: int | None = None

    def on_chat_model_start(self, serialized, messages, *, run_id, **kwargs):
        self._pending[str(run_id)] = time.time()

    def on_llm_end(self, response, *, run_id, **kwargs):
        started = self._pending.pop(str(run_id), time.time())
        latency_ms = int((time.time() - started) * 1000)

        try:
            generation = response.generations[0][0]
            message = generation.message
            usage = getattr(message, "usage_metadata", None) or {}
            provider = (message.response_metadata or {}).get("model_provider")
            model = (message.response_metadata or {}).get("model_name")
            raw_response = json.dumps(
                {
                    "content": message.content,
                    "tool_calls": getattr(message, "tool_calls", None),
                    "response_metadata": message.response_metadata,
                    "usage_metadata": usage,
                },
                ensure_ascii=False,
                default=str,
            )
        except Exception:
            usage, provider, model, raw_response = {}, None, None, None

        self.last_row_id = record_llm_call(
            node=self.node,
            provider=provider,
            model=model,
            input_tokens=usage.get("input_tokens", 0),
            output_tokens=usage.get("output_tokens", 0),
            total_tokens=usage.get("total_tokens", 0),
            latency_ms=latency_ms,
            raw_response=raw_response,
        )

    def record_structured_output(self, result) -> None:
        """Called from llm.py right after .invoke() returns the parsed
        Pydantic object -- writes it onto the row on_llm_end just created,
        so each dashboard row carries both the raw provider response and
        the app-parsed structured output."""
        if self.last_row_id is None:
            return

        try:
            payload = json.dumps(result.model_dump(), ensure_ascii=False, default=str)
        except Exception:
            return

        update_llm_structured_output(self.last_row_id, payload)

    def on_llm_error(self, error, *, run_id, **kwargs):
        started = self._pending.pop(str(run_id), time.time())
        latency_ms = int((time.time() - started) * 1000)

        record_llm_call(
            node=self.node,
            provider=None,
            model=None,
            input_tokens=0,
            output_tokens=0,
            total_tokens=0,
            latency_ms=latency_ms,
            status="error",
            error=str(error),
        )
