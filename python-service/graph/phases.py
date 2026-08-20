"""
Standard project-phase reference used by generate_plan_node (via
build_plan_prompt in prompts.py) so the final plan's phase structure is
consistent across projects instead of the LLM inventing arbitrary phase
names each time. The LLM may skip a phase that clearly doesn't apply to a
given project, but should not invent phases outside this list.
"""

DEFAULT_PHASES = [
    "Requirement Analysis",
    "Planning and Design",
    "Development / Implementation",
    "Integration",
    "Testing and Validation",
    "Deployment / Delivery",
    "Handover / Closure",
]
