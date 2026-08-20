import os

from langgraph.checkpoint.mysql.pymysql import PyMySQLSaver
from langgraph.graph import END, START, StateGraph

from graph import nodes
from graph.state import ProjectGraphState


def _checkpoint_conn_string() -> str:
    # Same TCP/Unix-socket switch as database.py -- MYSQL_SOCKET_PATH is
    # only set on Cloud Run.
    host = os.getenv("MYSQL_HOST", "127.0.0.1")
    port = os.getenv("MYSQL_PORT", "3306")
    user = os.getenv("MYSQL_USER", "root")
    password = os.getenv("MYSQL_PASSWORD", "")
    database = os.getenv("MYSQL_DATABASE", "project_delivery_orchestrator")
    socket_path = os.getenv("MYSQL_SOCKET_PATH")
    query = f"?unix_socket={socket_path}" if socket_path else ""
    return f"mysql://{user}:{password}@{host}:{port}/{database}{query}"


def make_builder() -> StateGraph:
    """
    Node/edge wiring only, no compile step -- shared by build_graph() (our
    FastAPI service's own checkpointer) and graph/dev_entry.py (the
    `langgraph dev` CLI, which injects its own local persistence and would
    conflict with a checkpointer we attach ourselves).
    """
    builder = StateGraph(ProjectGraphState)

    builder.add_node("scope_guard", nodes.scope_guard_node)
    builder.add_node("reject_input", nodes.reject_input_node)
    builder.add_node("extract_project", nodes.extract_project_node)
    builder.add_node("retrieve_business_rules", nodes.retrieve_business_rules_node)
    builder.add_node("validate_rules", nodes.validate_rules_node)
    builder.add_node("ask_clarification", nodes.ask_clarification_node)
    builder.add_node("classify_role", nodes.classify_role_node)
    builder.add_node("retrieve_employee_rules", nodes.retrieve_employee_rules_node)
    builder.add_node("employee_candidates", nodes.employee_candidates_node)
    builder.add_node("validate_employees", nodes.validate_employees_node)
    builder.add_node("generate_plan", nodes.generate_plan_node)

    builder.add_edge(START, "scope_guard")

    builder.add_conditional_edges(
        "scope_guard",
        nodes.route_after_scope,
        {"extract_project": "extract_project", "reject_input": "reject_input"},
    )

    builder.add_edge("reject_input", "scope_guard")
    builder.add_edge("extract_project", "retrieve_business_rules")
    builder.add_edge("retrieve_business_rules", "validate_rules")

    builder.add_conditional_edges(
        "validate_rules",
        nodes.route_after_rule_validation,
        {"ask_clarification": "ask_clarification", "classify_role": "classify_role"},
    )

    builder.add_edge("ask_clarification", "extract_project")
    builder.add_edge("classify_role", "retrieve_employee_rules")
    builder.add_edge("retrieve_employee_rules", "employee_candidates")
    builder.add_edge("employee_candidates", "validate_employees")
    builder.add_edge("validate_employees", "generate_plan")
    builder.add_edge("generate_plan", END)

    return builder


def build_graph():
    builder = make_builder()

    # PyMySQLSaver.from_conn_string is a context manager; keep the manager
    # itself alive at module scope (not just the checkpointer it yields) so
    # it isn't garbage-collected and its __exit__ doesn't close the
    # connection out from under a still-running FastAPI process. Checkpoints
    # live in the same MySQL database as everything else -- no local disk,
    # so this survives container restarts and works across multiple
    # instances.
    global _checkpointer_cm
    _checkpointer_cm = PyMySQLSaver.from_conn_string(_checkpoint_conn_string())
    checkpointer = _checkpointer_cm.__enter__()
    checkpointer.setup()

    return builder.compile(checkpointer=checkpointer)


_checkpointer_cm = None
graph = build_graph()
