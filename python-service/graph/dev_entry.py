"""
Entry point for `langgraph dev` (LangGraph Studio's local debugger) --
compiled WITHOUT our own checkpointer so the CLI's own local persistence
layer manages threads/state instead of conflicting with
python-service/storage/checkpoints.db, which the production FastAPI service
(graph/build.py:graph) owns.
"""

from graph.build import make_builder

graph = make_builder().compile()
