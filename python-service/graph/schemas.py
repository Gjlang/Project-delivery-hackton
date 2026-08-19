"""
Structured-output schemas for every LLM decision point in the project-creation
graph. Kept separate from state.py: these are per-call LLM contracts, state.py
is the persisted graph state shape.
"""

from typing import Literal, Optional

from pydantic import BaseModel, Field


class ScopeResult(BaseModel):
    valid_project_input: bool
    category: Literal[
        "project_description",
        "project_followup",
        "off_topic",
        "nonsense",
        "malicious_instruction",
    ]
    reason: str


class ExtractedFacts(BaseModel):
    project_name: Optional[str] = None
    project_type_hint: Optional[str] = None
    business_problem: Optional[str] = None
    expected_outcome: Optional[str] = None
    description: Optional[str] = None
    features: list[str] = Field(default_factory=list)
    user_roles: list[str] = Field(default_factory=list)
    integrations: list[str] = Field(default_factory=list)
    constraints: list[str] = Field(default_factory=list)
    dates: list[str] = Field(default_factory=list)
    other_facts: list[str] = Field(default_factory=list)


class RuleCheck(BaseModel):
    rule_code: str
    status: Literal["PASS", "NEEDS_INFORMATION", "FAIL", "NOT_APPLICABLE"]
    reason: str
    missing_information: list[str] = Field(default_factory=list)


class RuleValidationResult(BaseModel):
    results: list[RuleCheck]


class ClarificationResponse(BaseModel):
    question: str
    requested_information: list[str] = Field(default_factory=list)


SUPPORTED_ROLES = [
    "Software Engineer",
    "Frontend Engineer",
    "Backend Engineer",
    "Mobile Engineer",
    "Machine Learning Engineer",
    "Data Engineer",
    "DevOps Engineer",
    "QA Engineer",
    "UI/UX Designer",
    "Business Analyst",
    "Project Manager",
]


class RoleClassification(BaseModel):
    primary_role: Literal[
        "Software Engineer",
        "Frontend Engineer",
        "Backend Engineer",
        "Mobile Engineer",
        "Machine Learning Engineer",
        "Data Engineer",
        "DevOps Engineer",
        "QA Engineer",
        "UI/UX Designer",
        "Business Analyst",
        "Project Manager",
    ]
    reason: str


class EmployeeEligibilityResult(BaseModel):
    employee_id: int
    eligible: bool
    reason: str


class EmployeeValidationResult(BaseModel):
    evaluations: list[EmployeeEligibilityResult]


class TaskPlan(BaseModel):
    name: str
    description: str
    duration_days: int


class PhasePlan(BaseModel):
    phase_number: int
    phase_name: str
    duration_days: int
    tasks: list[TaskPlan] = Field(default_factory=list)


class FinalProjectPlan(BaseModel):
    project_name: str
    project_summary: str
    primary_role: str
    recommended_employee_id: Optional[int] = None
    recommended_employee_name: Optional[str] = None
    estimated_duration_days: int
    phases: list[PhasePlan] = Field(default_factory=list)
