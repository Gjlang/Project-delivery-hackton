"""
Persisted graph state. Kept as a compact structured `project` dict (not free
chat text) plus the message list, per the design goal of not re-sending the
whole rulebook/history to the LLM on every turn.
"""

from typing import Annotated, Optional, TypedDict

from langchain_core.messages import BaseMessage, HumanMessage
from langgraph.graph.message import add_messages


class ProjectGraphState(TypedDict):
    messages: Annotated[list[BaseMessage], add_messages]

    # The owning user's id -- isolation boundary for employees and rules
    # (business_rules, employee_rules, etc. are all per-user now too).
    owner_id: int

    latest_user_input: str

    scope_valid: bool

    project: dict

    relevant_business_rules: list[dict]
    validated_rule_codes: list[str]
    unresolved_rules: list[dict]

    primary_role: Optional[str]

    relevant_employee_rules: list[dict]
    employee_candidates: list[dict]
    recommended_employee: Optional[dict]

    final_plan: Optional[dict]

    analysis_status: str


def initial_state(owner_id: int, user_input: str) -> ProjectGraphState:
    return {
        "messages": [HumanMessage(content=user_input)],
        "owner_id": owner_id,
        "latest_user_input": user_input,
        "scope_valid": False,
        "project": {},
        "relevant_business_rules": [],
        "validated_rule_codes": [],
        "unresolved_rules": [],
        "primary_role": None,
        "relevant_employee_rules": [],
        "employee_candidates": [],
        "recommended_employee": None,
        "final_plan": None,
        "analysis_status": "gathering",
    }


def merge_unique(existing: list[str], incoming: list[str]) -> list[str]:
    seen = set(existing)
    result = list(existing)

    for item in incoming:
        normalized = item.strip()
        if normalized and normalized not in seen:
            result.append(normalized)
            seen.add(normalized)

    return result


def merge_project_facts(current: dict, new) -> dict:
    result = dict(current or {})

    scalar_fields = [
        "project_name",
        "project_type_hint",
        "business_problem",
        "expected_outcome",
        "description",
        "start_date",
        "end_date",
    ]

    for field in scalar_fields:
        value = getattr(new, field)
        if value:
            result[field] = value

    list_fields = [
        "features",
        "user_roles",
        "integrations",
        "constraints",
        "dates",
        "other_facts",
    ]

    for field in list_fields:
        result[field] = merge_unique(result.get(field, []), getattr(new, field))

    return result


def project_to_search_queries(project: dict) -> list[str]:
    queries = []

    if project.get("description"):
        queries.append(project["description"])

    if project.get("business_problem"):
        queries.append("Business problem: " + project["business_problem"])

    if project.get("expected_outcome"):
        queries.append("Expected outcome: " + project["expected_outcome"])

    for feature in project.get("features", []):
        queries.append(f"Project feature: {feature}")

    for integration in project.get("integrations", []):
        queries.append(f"External integration: {integration}")

    for constraint in project.get("constraints", []):
        queries.append(f"Project constraint: {constraint}")

    return queries
