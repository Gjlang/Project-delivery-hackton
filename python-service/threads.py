"""
FastAPI router for the project-creation LangGraph orchestrator. Laravel's
ProjectCreationChatService proxies to these endpoints -- Laravel supplies the
thread_id (its own project_creation_sessions.id cast to string) so both
systems stay keyed the same way.
"""

from langchain_core.messages import AIMessage, HumanMessage
from langgraph.types import Command
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from graph.build import graph
from graph.state import initial_state

router = APIRouter(prefix="/threads", tags=["threads"])

WELCOME_MESSAGE = (
    "Describe the project your company wants to build. I'll ask follow-up "
    "questions and check it against your company's rules as we go."
)


class StartThreadRequest(BaseModel):
    thread_id: str
    owner_id: int


class PostMessageRequest(BaseModel):
    owner_id: int
    message: str


def _config(thread_id: str) -> dict:
    return {"configurable": {"thread_id": thread_id}}


def _serialize_messages(messages: list) -> list[dict]:
    out = []
    for m in messages:
        if isinstance(m, HumanMessage):
            role = "user"
        elif isinstance(m, AIMessage):
            role = "assistant"
        else:
            continue
        out.append({"role": role, "content": m.content})
    return out


def _last_assistant_message(messages: list) -> str:
    for m in reversed(messages):
        if isinstance(m, AIMessage):
            return m.content
    return ""


def _turn_response(invoke_result: dict, thread_id: str) -> dict:
    snapshot = graph.get_state(_config(thread_id))
    interrupted = bool(snapshot.next)

    clarifications = []
    assistant_message = _last_assistant_message(invoke_result.get("messages", []))

    # Read the interrupt payload from the state snapshot itself (not from
    # invoke()'s return value) so this works identically whether we just
    # invoked the graph or are re-fetching an already-paused thread (GET
    # /threads/{id}, or POST /threads on an existing thread) -- invoke()'s
    # "__interrupt__" key is only present on the call that triggered the
    # pause, but the snapshot's tasks carry it for as long as it stays paused.
    if interrupted:
        for task in snapshot.tasks:
            task_interrupts = getattr(task, "interrupts", None)
            if task_interrupts:
                payload = task_interrupts[0].value
                assistant_message = payload.get("message", assistant_message)
                if payload.get("type") == "clarification":
                    clarifications = [
                        {
                            "question": payload.get("message"),
                            "requested_information": payload.get("requested_information", []),
                        }
                    ]
                break

    state = snapshot.values

    return {
        "assistant_message": assistant_message,
        "draft": state.get("project", {}),
        "primary_role": state.get("primary_role"),
        "recommended_employee": state.get("recommended_employee"),
        "clarifications": clarifications,
        "analysis_status": state.get("analysis_status", "gathering"),
        "final_plan": state.get("final_plan"),
    }


@router.post("")
def start_thread(payload: StartThreadRequest):
    snapshot = graph.get_state(_config(payload.thread_id))

    if snapshot.values:
        return _turn_response({"messages": snapshot.values.get("messages", [])}, payload.thread_id)

    return {
        "assistant_message": WELCOME_MESSAGE,
        "draft": {},
        "primary_role": None,
        "recommended_employee": None,
        "clarifications": [],
        "analysis_status": "gathering",
        "final_plan": None,
    }


@router.get("/{thread_id}")
def get_thread(thread_id: str):
    snapshot = graph.get_state(_config(thread_id))

    if not snapshot.values:
        return {
            "assistant_message": WELCOME_MESSAGE,
            "draft": {},
            "primary_role": None,
            "recommended_employee": None,
            "clarifications": [],
            "analysis_status": "gathering",
            "final_plan": None,
            "messages": [],
        }

    response = _turn_response({"messages": snapshot.values.get("messages", [])}, thread_id)
    response["messages"] = _serialize_messages(snapshot.values.get("messages", []))
    return response


@router.post("/{thread_id}/messages")
def post_message(thread_id: str, payload: PostMessageRequest):
    config = _config(thread_id)
    snapshot = graph.get_state(config)

    if not snapshot.values:
        input_ = initial_state(payload.owner_id, payload.message)
    elif snapshot.next:
        input_ = Command(resume=payload.message)
    else:
        input_ = {
            "owner_id": payload.owner_id,
            "latest_user_input": payload.message,
            "messages": [HumanMessage(content=payload.message)],
        }

    result = graph.invoke(input_, config=config)
    return _turn_response(result, thread_id)


@router.post("/{thread_id}/confirm")
def confirm_thread(thread_id: str):
    snapshot = graph.get_state(_config(thread_id))

    if not snapshot.values or snapshot.values.get("analysis_status") != "ready":
        raise HTTPException(status_code=422, detail={"error_code": "NOT_READY", "message": "Required information is still missing or unresolved."})

    state = snapshot.values
    rules_by_code = {r["rule_code"]: r for r in state.get("relevant_business_rules", [])}

    rule_matches = [
        {
            "rule_code": code,
            "rule_id": rules_by_code.get(code, {}).get("rule_id"),
            "category": rules_by_code.get(code, {}).get("category", "business_rules"),
            "reason": "Validated against project description.",
        }
        for code in state.get("validated_rule_codes", [])
    ]

    return {
        "final_plan": state.get("final_plan"),
        "draft": state.get("project", {}),
        "primary_role": state.get("primary_role"),
        "recommended_employee": state.get("recommended_employee"),
        "rule_matches": rule_matches,
    }


@router.post("/{thread_id}/cancel")
def cancel_thread(thread_id: str):
    return {"status": "cancelled"}
