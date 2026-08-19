from langchain_core.messages import AIMessage, HumanMessage
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
    queries = project_to_search_queries(state.get("project", {}))
    if not queries:
        return {"relevant_business_rules": state.get("relevant_business_rules", [])}

    rules = retrieval.retrieve_business_rules(
        queries=queries,
        company_id=state.get("company_id"),
        limit_per_query=4,
    )

    # Carry forward previously unresolved rules even if they drop out of the
    # fresh top-k, so an answered-but-not-yet-revalidated rule isn't lost.
    merged = {rule["rule_code"]: rule for rule in rules}
    for item in state.get("unresolved_rules", []):
        code = item["rule_code"]
        if code not in merged:
            merged[code] = {
                "score": 0.0,
                "rule_code": code,
                "rule_id": None,
                "category": "business_rules",
                "title": None,
                "section": None,
                "rule_text": item.get("rule_text", ""),
            }

    return {"relevant_business_rules": list(merged.values())}


def _rules_to_validate(state: ProjectGraphState) -> list[dict]:
    passed = set(state.get("validated_rule_codes", []))
    unresolved_codes = {item["rule_code"] for item in state.get("unresolved_rules", [])}

    selected = {}
    for rule in state.get("relevant_business_rules", []):
        code = rule["rule_code"]
        if code not in passed or code in unresolved_codes:
            selected[code] = rule

    return list(selected.values())


def validate_rules_node(state: ProjectGraphState):
    rules = _rules_to_validate(state)
    if not rules:
        return {"unresolved_rules": []}

    result = rule_validator_llm.invoke(build_rule_validation_prompt(state.get("project", {}), rules))

    rules_by_code = {rule["rule_code"]: rule for rule in rules}
    passed = set(state.get("validated_rule_codes", []))
    unresolved = []

    for item in result.results:
        if item.status == "PASS" or item.status == "NOT_APPLICABLE":
            passed.add(item.rule_code)
        elif item.status in ("NEEDS_INFORMATION", "FAIL"):
            entry = item.model_dump()
            entry["rule_text"] = rules_by_code.get(item.rule_code, {}).get("rule_text", "")
            unresolved.append(entry)

    return {"validated_rule_codes": list(passed), "unresolved_rules": unresolved}


def route_after_rule_validation(state: ProjectGraphState):
    return "ask_clarification" if state.get("unresolved_rules") else "classify_role"


def ask_clarification_node(state: ProjectGraphState):
    clarification = question_llm.invoke(build_clarification_prompt(state.get("unresolved_rules", [])))

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

    rules = retrieval.retrieve_employee_rules(query=query, company_id=state.get("company_id"), limit=6)
    return {"relevant_employee_rules": rules}


def employee_candidates_node(state: ProjectGraphState):
    employees = employee_repo.get_employees_by_role(
        company_id=state.get("company_id"),
        role=state.get("primary_role"),
    )
    return {"employee_candidates": employees}


def validate_employees_node(state: ProjectGraphState):
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

    eligible_ids = {e.employee_id for e in result.evaluations if e.eligible}
    eligible = [e for e in candidates if e["id"] in eligible_ids]

    if not eligible:
        return {"recommended_employee": None}

    eligible.sort(key=lambda e: e["active_project_count"])
    return {"recommended_employee": eligible[0]}


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
