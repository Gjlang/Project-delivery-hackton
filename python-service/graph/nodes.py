from langchain_core.messages import AIMessage, HumanMessage
from langchain_core.runnables import RunnableConfig
from langgraph.types import interrupt

from graph import employees as employee_repo
from graph import retrieval
from graph.llm import (
    employee_validator_llm,
    extract_llm,
    plan_llm,
    question_llm,
    role_llm,
    rule_validator_llm,
    scope_llm,
)
from graph.prompts import (
    build_clarification_prompt,
    build_employee_validation_prompt,
    build_extraction_prompt,
    build_plan_prompt,
    build_role_prompt,
    build_rule_validation_prompt,
    build_scope_prompt,
)
from graph.schemas import SUPPORTED_ROLES
from graph.state import ProjectGraphState, merge_project_facts, project_to_search_queries
from tracking import record_employee_check, record_rule_check


def _thread_id(config: RunnableConfig | None) -> str | None:
    return (config or {}).get("configurable", {}).get("thread_id")


def _resolve_user_input(state: ProjectGraphState) -> str:
    """
    Prefer the explicit `latest_user_input` field (always set when the graph
    is invoked through our own FastAPI service via graph/state.py's
    initial_state()), but fall back to the last HumanMessage in `messages`
    -- callers that invoke the compiled graph directly (e.g. LangGraph
    Studio's generic input form, which only lets you add Messages) never
    populate latest_user_input at all, so a hard state["latest_user_input"]
    lookup would KeyError.
    """
    explicit = state.get("latest_user_input")
    if explicit:
        return explicit

    for m in reversed(state.get("messages", [])):
        if isinstance(m, HumanMessage):
            return m.content

    return ""


# -- Scope guard --------------------------------------------------------

def route_from_start(state: ProjectGraphState):
    return "already_planned" if state.get("analysis_status") == "ready" else "scope_guard"


def already_planned_node(state: ProjectGraphState):
    message = (
        "This project's plan has already been created -- I can't generate "
        "another one in this chat. Start a New Chat to plan a different "
        "project, or use \"Review & Create Project\" to confirm this one."
    )

    user_reply = interrupt({"type": "already_planned", "message": message})

    return {
        "latest_user_input": str(user_reply),
        "messages": [AIMessage(content=message), HumanMessage(content=str(user_reply))],
    }


def scope_guard_node(state: ProjectGraphState):
    result = scope_llm.invoke(build_scope_prompt(_resolve_user_input(state)))
    return {"scope_valid": result.valid_project_input}


def route_after_scope(state: ProjectGraphState):
    return "extract_project" if state.get("scope_valid") else "reject_input"


def reject_input_node(state: ProjectGraphState):
    message = (
        "I can help with creating and refining a project plan. Please "
        "describe the project you want to build or answer the current "
        "project question."
    )

    user_reply = interrupt({"type": "project_input_required", "message": message})

    return {
        "latest_user_input": str(user_reply),
        "messages": [AIMessage(content=message), HumanMessage(content=str(user_reply))],
    }


# -- Extraction -----------------------------------------------------------

def extract_project_node(state: ProjectGraphState):
    extracted = extract_llm.invoke(build_extraction_prompt(_resolve_user_input(state)))
    project = merge_project_facts(state.get("project", {}), extracted)
    return {"project": project}


# -- Business rule retrieval + validation ---------------------------------

def retrieve_business_rules_node(state: ProjectGraphState):
    # Real Qdrant semantic retrieval, but limit_per_query defaults to the
    # user's total active rule count (see retrieval.retrieve_business_rules)
    # so a rule can never silently drop out of results for scoring low.
    rules = retrieval.retrieve_business_rules(
        queries=project_to_search_queries(state.get("project", {})),
        owner_id=state.get("owner_id"),
    )

    return {"relevant_business_rules": rules}


def _rules_to_validate(state: ProjectGraphState) -> list[dict]:
    passed = set(state.get("validated_rule_codes", []))
    unresolved_codes = {item["rule_code"] for item in state.get("unresolved_rules", [])}

    selected = {}
    for rule in state.get("relevant_business_rules", []):
        code = rule["rule_code"]
        if code not in passed or code in unresolved_codes:
            selected[code] = rule

    return list(selected.values())


def validate_rules_node(state: ProjectGraphState, config: RunnableConfig | None = None):
    rules = _rules_to_validate(state)
    if not rules:
        return {"unresolved_rules": []}

    result = rule_validator_llm.invoke(build_rule_validation_prompt(state.get("project", {}), rules))

    thread_id = _thread_id(config)
    rules_by_code = {rule["rule_code"]: rule for rule in rules}
    passed = set(state.get("validated_rule_codes", []))
    unresolved = []

    for item in result.results:
        category = rules_by_code.get(item.rule_code, {}).get("category", "business_rules")
        record_rule_check(item.rule_code, category, item.status, item.reason, thread_id=thread_id)

        if item.status == "PASS":
            passed.add(item.rule_code)
        elif item.status in ("NEEDS_INFORMATION", "FAIL"):
            entry = item.model_dump()
            entry["rule_text"] = rules_by_code.get(item.rule_code, {}).get("rule_text", "")
            unresolved.append(entry)

    return {"validated_rule_codes": list(passed), "unresolved_rules": unresolved}


def route_after_rule_validation(state: ProjectGraphState):
    return "ask_clarification" if state.get("unresolved_rules") else "classify_role"


def ask_clarification_node(state: ProjectGraphState):
    clarification = question_llm.invoke(
        build_clarification_prompt(state.get("unresolved_rules", []), state.get("project", {}))
    )

    answer = interrupt(
        {
            "type": "clarification",
            "message": clarification.question,
            "requested_information": clarification.requested_information,
        }
    )

    return {
        "latest_user_input": str(answer),
        "analysis_status": "needs_clarification",
        "messages": [
            AIMessage(content=clarification.question),
            HumanMessage(content=str(answer)),
        ],
    }


# -- Role + employee ---------------------------------------------------

def classify_role_node(state: ProjectGraphState):
    result = role_llm.invoke(build_role_prompt(state.get("project", {}), SUPPORTED_ROLES))
    return {"primary_role": result.primary_role}


def retrieve_employee_rules_node(state: ProjectGraphState):
    query = (
        "Staffing and workload rules for assigning a "
        f"{state.get('primary_role')} to a new project."
    )

    rules = retrieval.retrieve_employee_rules(query=query, owner_id=state.get("owner_id"))
    return {"relevant_employee_rules": rules}


def employee_candidates_node(state: ProjectGraphState):
    employees = employee_repo.get_employees_by_role(
        owner_id=state.get("owner_id"),
        role=state.get("primary_role"),
    )
    return {"employee_candidates": employees}


def validate_employees_node(state: ProjectGraphState, config: RunnableConfig | None = None):
    candidates = state.get("employee_candidates", [])
    if not candidates:
        return {"recommended_employee": None}

    result = employee_validator_llm.invoke(
        build_employee_validation_prompt(
            role=state.get("primary_role"),
            employees=candidates,
            rules=state.get("relevant_employee_rules", []),
        )
    )

    thread_id = _thread_id(config)
    candidates_by_id = {e["id"]: e for e in candidates}
    eligible_ids = {e.employee_id for e in result.evaluations if e.eligible}
    eligible = [e for e in candidates if e["id"] in eligible_ids]
    eligible.sort(key=lambda e: e["active_project_count"])
    recommended = eligible[0] if eligible else None

    for evaluation in result.evaluations:
        employee = candidates_by_id.get(evaluation.employee_id, {})
        record_employee_check(
            employee_id=evaluation.employee_id,
            employee_name=employee.get("name", "unknown"),
            role=state.get("primary_role") or "unknown",
            eligible=evaluation.eligible,
            reason=evaluation.reason,
            recommended=bool(recommended and recommended["id"] == evaluation.employee_id),
            thread_id=thread_id,
        )

    return {"recommended_employee": recommended}


# -- Plan generation --------------------------------------------------

def generate_plan_node(state: ProjectGraphState):
    result = plan_llm.invoke(
        build_plan_prompt(
            project=state.get("project", {}),
            role=state.get("primary_role"),
            employee=state.get("recommended_employee"),
            relevant_rules=state.get("relevant_business_rules", []),
        )
    )

    return {
        "final_plan": result.model_dump(),
        "analysis_status": "ready",
        "messages": [AIMessage(content="Project plan created successfully.")],
    }
