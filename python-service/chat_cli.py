"""
Pure-terminal chat loop for the project-creation graph -- no browser, no
Studio, no HTTP server. Uses its own throwaway in-memory checkpointer
(separate from storage/checkpoints.db, which the production FastAPI service
owns) so this is safe to run alongside `uvicorn main:app`.

Usage:
    venv/Scripts/python.exe chat_cli.py
"""

import json

from langchain_core.messages import AIMessage, HumanMessage
from langgraph.checkpoint.memory import InMemorySaver
from langgraph.types import Command

from graph.build import make_builder
from graph.state import initial_state

OWNER_ID = 1


def last_assistant_message(messages: list) -> str | None:
    for m in reversed(messages):
        if isinstance(m, AIMessage):
            return m.content
    return None


def main() -> None:
    app = make_builder().compile(checkpointer=InMemorySaver())
    config = {"configurable": {"thread_id": "local-cli"}}

    print("=== ProjectFlow AI -- local terminal chat (Ctrl+C to quit) ===\n")

    user_input = input("You: ").strip()
    step = initial_state(OWNER_ID, user_input)

    while True:
        print("... thinking ...")
        app.invoke(step, config=config)
        snapshot = app.get_state(config)
        state = snapshot.values

        if snapshot.next:
            question = None
            for task in snapshot.tasks:
                task_interrupts = getattr(task, "interrupts", None)
                if task_interrupts:
                    question = task_interrupts[0].value.get("message")
                    break

            print(f"\nAssistant: {question}\n")
            answer = input("You: ").strip()
            step = Command(resume=answer)
            continue

        message = last_assistant_message(state.get("messages", []))
        if message:
            print(f"\nAssistant: {message}\n")

        if state.get("analysis_status") == "ready":
            print("=== FINAL PLAN ===")
            print(json.dumps(state.get("final_plan"), indent=2))
            break

        user_input = input("You: ").strip()
        step = {
            "owner_id": OWNER_ID,
            "latest_user_input": user_input,
            "messages": [HumanMessage(content=user_input)],
        }


if __name__ == "__main__":
    try:
        main()
    except (KeyboardInterrupt, EOFError):
        print("\nBye.")
