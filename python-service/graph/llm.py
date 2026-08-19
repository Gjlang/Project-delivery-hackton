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

load_dotenv()


llm = ChatGoogleGenerativeAI(
    model=os.getenv("GEMINI_MODEL", "gemini-2.5-flash"),
    temperature=0,
    google_api_key=os.getenv("GOOGLE_API_KEY"),
)


scope_llm = llm.with_structured_output(ScopeResult, method="json_schema")
extract_llm = llm.with_structured_output(ExtractedFacts, method="json_schema")
rule_validator_llm = llm.with_structured_output(RuleValidationResult, method="json_schema")
question_llm = llm.with_structured_output(ClarificationResponse, method="json_schema")
role_llm = llm.with_structured_output(RoleClassification, method="json_schema")
employee_validator_llm = llm.with_structured_output(EmployeeValidationResult, method="json_schema")
plan_llm = llm.with_structured_output(FinalProjectPlan, method="json_schema")
