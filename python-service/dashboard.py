"""
Standalone Gradio dashboard for the project-creation graph's backend
observability -- reads directly from storage/tracking.db (written by
tracking.py, which every LLM call and rule/employee check in graph/nodes.py
logs to). Doesn't touch the running FastAPI service or the graph itself.

Usage:
    venv/Scripts/python.exe dashboard.py
Then open the printed local URL in a browser.
"""

import json
import sqlite3
from pathlib import Path

import gradio as gr
import pandas as pd

DB_PATH = Path(__file__).parent / "storage" / "tracking.db"

# Gemini free-tier daily cap per model -- see GenerateRequestsPerDayPerProjectPerModel-FreeTier
GEMINI_FREE_DAILY_LIMIT = 20

ALL_THREADS = "All threads"


def _query(sql: str, params: tuple = ()) -> pd.DataFrame:
    if not DB_PATH.exists():
        return pd.DataFrame()

    conn = sqlite3.connect(DB_PATH)
    try:
        return pd.read_sql_query(sql, conn, params=params)
    finally:
        conn.close()


def _thread_choices() -> list[str]:
    df = _query(
        """
        SELECT thread_id, MAX(ts) as last_seen FROM (
            SELECT thread_id, timestamp as ts FROM llm_calls WHERE thread_id IS NOT NULL
            UNION ALL
            SELECT thread_id, timestamp as ts FROM rule_checks WHERE thread_id IS NOT NULL
            UNION ALL
            SELECT thread_id, timestamp as ts FROM employee_checks WHERE thread_id IS NOT NULL
        )
        GROUP BY thread_id
        ORDER BY last_seen DESC
        """
    )
    threads = df["thread_id"].tolist() if not df.empty else []
    return [ALL_THREADS] + threads


def _thread_filter_sql(thread_id: str) -> tuple[str, tuple]:
    if not thread_id or thread_id == ALL_THREADS:
        return "", ()
    return "WHERE thread_id = ?", (thread_id,)


# -- Requests / tokens ----------------------------------------------------

def summary_metrics(thread_id: str):
    where, params = _thread_filter_sql(thread_id)
    df = _query(f"SELECT * FROM llm_calls {where}", params)
    if df.empty:
        return "No LLM calls recorded yet.", "", pd.DataFrame()

    total_calls = len(df)
    total_tokens = int(df["total_tokens"].sum())
    errors = int((df["status"] == "error").sum())

    today = pd.Timestamp.utcnow().strftime("%Y-%m-%d")
    df["date"] = df["timestamp"].str.slice(0, 10)
    today_df = df[df["date"] == today]

    gemini_today = int((today_df["provider"] == "google_genai").sum())
    ollama_today = int((today_df["provider"] == "ollama").sum())
    remaining = max(0, GEMINI_FREE_DAILY_LIMIT - gemini_today)

    overview = (
        f"**Total calls:** {total_calls}  \n"
        f"**Total tokens:** {total_tokens:,}  \n"
        f"**Errors:** {errors}"
    )

    today_summary = (
        f"**Gemini calls today:** {gemini_today} / {GEMINI_FREE_DAILY_LIMIT} free-tier daily limit "
        f"(resets ~midnight Pacific Time)  \n"
        f"**Estimated remaining:** {remaining}  \n"
        f"**Ollama calls today (unlimited/local):** {ollama_today}"
    )

    by_node = df.groupby("node").agg(
        calls=("id", "count"),
        total_tokens=("total_tokens", "sum"),
        avg_latency_ms=("latency_ms", "mean"),
    ).reset_index().sort_values("calls", ascending=False)
    by_node["avg_latency_ms"] = by_node["avg_latency_ms"].round(0).astype(int)

    return overview, today_summary, by_node


def daily_usage(thread_id: str):
    """
    Per-day, per-provider breakdown -- requests and tokens consumed each
    day, not just an all-time cumulative total. This is the table to check
    after prompting to see exactly what that turn cost.
    """
    where, params = _thread_filter_sql(thread_id)
    df = _query(f"SELECT timestamp, provider, total_tokens FROM llm_calls {where}", params)
    if df.empty:
        return pd.DataFrame(columns=["date", "provider", "requests", "total_tokens", "avg_tokens_per_request"])

    df["date"] = df["timestamp"].str.slice(0, 10)
    df["provider"] = df["provider"].fillna("unknown")

    grouped = df.groupby(["date", "provider"]).agg(
        requests=("total_tokens", "count"),
        total_tokens=("total_tokens", "sum"),
    ).reset_index()
    grouped["avg_tokens_per_request"] = (grouped["total_tokens"] / grouped["requests"]).round(0).astype(int)

    # Gemini's free-tier daily request cap only applies to google_genai calls.
    grouped["requests_vs_free_limit"] = grouped.apply(
        lambda r: f"{r['requests']} / {GEMINI_FREE_DAILY_LIMIT}" if r["provider"] == "google_genai" else "unlimited (local)",
        axis=1,
    )

    return grouped.sort_values(["date", "provider"], ascending=[False, True])


def recent_calls(thread_id: str, limit: int = 50):
    where, params = _thread_filter_sql(thread_id)
    return _query(
        f"SELECT timestamp, thread_id, node, provider, model, input_tokens, output_tokens, total_tokens, latency_ms, status "
        f"FROM llm_calls {where} ORDER BY id DESC LIMIT ?",
        params + (limit,),
    )


def tokens_by_node_chart(thread_id: str):
    where, params = _thread_filter_sql(thread_id)
    df = _query(f"SELECT node, total_tokens FROM llm_calls {where}", params)
    if df.empty:
        return pd.DataFrame({"node": [], "total_tokens": []})
    return df.groupby("node", as_index=False)["total_tokens"].sum()


def calls_by_provider_chart(thread_id: str):
    where, params = _thread_filter_sql(thread_id)
    return _query(f"SELECT provider, COUNT(*) as calls FROM llm_calls {where} GROUP BY provider", params)


# -- LLM call inspector -- full return structure per call ------------------

def _call_choices(thread_id: str) -> list[str]:
    where, params = _thread_filter_sql(thread_id)
    df = _query(f"SELECT id, node, timestamp FROM llm_calls {where} ORDER BY id DESC LIMIT 100", params)
    if df.empty:
        return []
    return [f"#{row.id} · {row.node} · {row.timestamp}" for row in df.itertuples()]


def _parse_call_id(label: str | None) -> int | None:
    if not label:
        return None
    try:
        return int(label.split("·")[0].strip().lstrip("#"))
    except (ValueError, IndexError):
        return None


def call_detail(label: str | None):
    call_id = _parse_call_id(label)
    if call_id is None:
        return {}, {}

    df = _query("SELECT structured_output, raw_response FROM llm_calls WHERE id = ?", (call_id,))
    if df.empty:
        return {}, {}

    row = df.iloc[0]

    try:
        structured = json.loads(row["structured_output"]) if row["structured_output"] else {
            "info": "No structured output captured (call may have errored before returning)."
        }
    except (TypeError, json.JSONDecodeError):
        structured = {"info": "Could not parse stored structured output."}

    try:
        raw = json.loads(row["raw_response"]) if row["raw_response"] else {"info": "No raw response captured."}
    except (TypeError, json.JSONDecodeError):
        raw = {"info": "Could not parse stored raw response."}

    return structured, raw


# -- Rules tracking ---------------------------------------------------

def rule_checks(thread_id: str, limit: int = 100):
    where, params = _thread_filter_sql(thread_id)
    return _query(
        f"SELECT timestamp, thread_id, rule_code, category, status, reason FROM rule_checks {where} ORDER BY id DESC LIMIT ?",
        params + (limit,),
    )


def unresolved_rule_checks(thread_id: str, limit: int = 100):
    """Only FAIL / NEEDS_INFORMATION -- the ones that actually need attention."""
    where, params = _thread_filter_sql(thread_id)
    status_clause = "status IN ('FAIL', 'NEEDS_INFORMATION')"
    where = f"{where} AND {status_clause}" if where else f"WHERE {status_clause}"
    return _query(
        f"SELECT timestamp, thread_id, rule_code, category, status, reason FROM rule_checks {where} ORDER BY id DESC LIMIT ?",
        params + (limit,),
    )


def rule_status_chart(thread_id: str):
    where, params = _thread_filter_sql(thread_id)
    return _query(f"SELECT status, COUNT(*) as count FROM rule_checks {where} GROUP BY status", params)


# -- Employee checks ----------------------------------------------------

def employee_checks(thread_id: str, limit: int = 100):
    where, params = _thread_filter_sql(thread_id)
    df = _query(
        f"SELECT timestamp, thread_id, employee_name, role, eligible, recommended, reason "
        f"FROM employee_checks {where} ORDER BY id DESC LIMIT ?",
        params + (limit,),
    )
    if not df.empty:
        df["eligible"] = df["eligible"].map({1: "Yes", 0: "No"})
        df["recommended"] = df["recommended"].map({1: "★ Recommended", 0: ""})
    return df


def refresh_all(thread_id: str, current_call: str | None):
    overview, today_summary, by_node = summary_metrics(thread_id)

    call_choices = _call_choices(thread_id)
    call_value = current_call if current_call in call_choices else (call_choices[0] if call_choices else None)
    structured, raw = call_detail(call_value)

    return (
        gr.update(choices=_thread_choices(), value=thread_id or ALL_THREADS),
        overview,
        today_summary,
        daily_usage(thread_id),
        by_node,
        recent_calls(thread_id),
        tokens_by_node_chart(thread_id),
        calls_by_provider_chart(thread_id),
        unresolved_rule_checks(thread_id),
        rule_checks(thread_id),
        rule_status_chart(thread_id),
        employee_checks(thread_id),
        gr.update(choices=call_choices, value=call_value),
        structured,
        raw,
    )


with gr.Blocks(title="ProjectFlow AI -- Backend Tracker") as demo:
    gr.Markdown("# ProjectFlow AI -- Backend Performance Tracker")
    gr.Markdown(
        "Reads live from `python-service/storage/tracking.db`. Nothing here auto-updates -- "
        "prompt the chatbot, then click **🔄 Refresh** to pull in what just happened."
    )

    with gr.Row():
        thread_dropdown = gr.Dropdown(choices=[ALL_THREADS], value=ALL_THREADS, label="Thread ID (conversation)", scale=3)
        refresh_btn = gr.Button("🔄 Refresh", variant="primary", scale=1)

    with gr.Tab("Token & Request Usage"):
        with gr.Row():
            overview_md = gr.Markdown()
            today_md = gr.Markdown()

        gr.Markdown("### Daily usage (requests + tokens per day, per provider)")
        daily_usage_table = gr.Dataframe(interactive=False)

        gr.Markdown("### Calls by node")
        by_node_table = gr.Dataframe(interactive=False)

        with gr.Row():
            tokens_chart = gr.BarPlot(x="node", y="total_tokens", title="Total tokens by node")
            provider_chart = gr.BarPlot(x="provider", y="calls", title="Calls by provider")

        gr.Markdown("### Recent calls")
        recent_table = gr.Dataframe(interactive=False)

    with gr.Tab("Rules Tracking"):
        gr.Markdown("### ⚠️ Needs attention (FAIL / NEEDS_INFORMATION only)")
        unresolved_rule_table = gr.Dataframe(interactive=False)

        gr.Markdown("### All rule checks")
        rule_status_plot = gr.BarPlot(x="status", y="count", title="Rule check outcomes")
        rule_table = gr.Dataframe(interactive=False)

    with gr.Tab("Employee Recommendations"):
        gr.Markdown("Every employee evaluated during `validate_employees` -- who was eligible, and who got recommended.")
        employee_table = gr.Dataframe(interactive=False)

    with gr.Tab("LLM Call Inspector"):
        gr.Markdown(
            "Pick a call to see exactly what the LLM returned -- both the app-parsed structured "
            "output and the raw provider response (content + metadata), for debugging why the AI "
            "asked or decided something."
        )
        call_dropdown = gr.Dropdown(choices=[], label="Call", scale=3)
        with gr.Row():
            structured_json = gr.JSON(label="Structured Output (parsed)")
            raw_json = gr.JSON(label="Raw Provider Response")

    outputs = [
        thread_dropdown, overview_md, today_md, daily_usage_table, by_node_table, recent_table,
        tokens_chart, provider_chart, unresolved_rule_table, rule_table, rule_status_plot, employee_table,
        call_dropdown, structured_json, raw_json,
    ]

    demo.load(fn=refresh_all, inputs=[thread_dropdown, call_dropdown], outputs=outputs)
    refresh_btn.click(fn=refresh_all, inputs=[thread_dropdown, call_dropdown], outputs=outputs)
    thread_dropdown.change(fn=refresh_all, inputs=[thread_dropdown, call_dropdown], outputs=outputs)
    call_dropdown.change(fn=call_detail, inputs=[call_dropdown], outputs=[structured_json, raw_json])


if __name__ == "__main__":
    demo.launch()
