"""
Category-prefix mapping, mirroring backend-laravel/config/knowledge_rules.php
exactly -- Laravel still sends/validates the short prefix (BR/CP/EW/SC/TS/AG)
and the existing `documents.category` column already stores that prefix, so
this must stay in lockstep with the PHP config rather than using the rule
tables' full names as the public-facing category value.
"""

RULE_TABLE_BY_PREFIX = {
    "BR": "business_rules",
    "EW": "employee_rules",
    "SC": "security_compliance",
    "TS": "technical_standards",
    "AG": "approval_governance",
    "TR": "testing_result_rules",
}


def table_for_prefix(prefix: str) -> str:
    try:
        return RULE_TABLE_BY_PREFIX[prefix]
    except KeyError:
        raise ValueError(f"Unsupported category prefix: {prefix}")
