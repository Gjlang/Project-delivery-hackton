import os

from dotenv import load_dotenv
from langchain_google_genai import ChatGoogleGenerativeAI

from graph.schemas import (
    ClarificationResponse,
    EmployeeValidationResult,
    ExtractedFacts,
    FinalProjectPlan,
    RoleClassification,
    RuleValidationResult,
    ScopeResult,
)
from tracking import UsageTracker


class _TrackedRunnable:
    """Wraps a with_structured_output() Runnable so its parsed return value
    can be logged alongside the raw response the UsageTracker callback
    already captures -- the callback only sees the pre-parse LLM response,
    not the structured object .invoke() returns, so that has to be recorded
    here at the call site instead."""

    def __init__(self, bound_llm, tracker: UsageTracker):
        self._bound = bound_llm.with_config({"callbacks": [tracker]})
        self._tracker = tracker

    def invoke(self, *args, **kwargs):
        result = self._bound.invoke(*args, **kwargs)
        self._tracker.record_structured_output(result)
        return result


def _tracked(bound_llm, node: str):
    """Attach a per-node usage tracker directly on the bound LLM instance,
    so every .invoke() through it is logged regardless of call site."""
    return _TrackedRunnable(bound_llm, UsageTracker(node))

load_dotenv()


llm = ChatGoogleGenerativeAI(
    model=os.getenv("GEMINI_MODEL", "gemini-2.5-flash"),
    temperature=0,
    google_api_key=os.getenv("GOOGLE_API_KEY"),
)


scope_llm = _tracked(llm.with_structured_output(ScopeResult, method="json_schema"), "scope_guard")
extract_llm = _tracked(llm.with_structured_output(ExtractedFacts, method="json_schema"), "extract_project")
rule_validator_llm = _tracked(llm.with_structured_output(RuleValidationResult, method="json_schema"), "validate_rules")
question_llm = _tracked(llm.with_structured_output(ClarificationResponse, method="json_schema"), "ask_clarification")
role_llm = _tracked(llm.with_structured_output(RoleClassification, method="json_schema"), "classify_role")
employee_validator_llm = _tracked(llm.with_structured_output(EmployeeValidationResult, method="json_schema"), "validate_employees")
plan_llm = _tracked(llm.with_structured_output(FinalProjectPlan, method="json_schema"), "generate_plan")
