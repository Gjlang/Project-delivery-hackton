"""
Rule extraction, faithful port of
backend-laravel/app/Services/Rules/KnowledgeRuleChunker.php.

Line-by-line scan: detects rule headers like "BR-024 - Web Application
Project" (bullet/dash prefix optional, em-dash/hyphen/colon separator),
tracks the current section from short heading-like lines, and groups body
lines under the most recent rule header until the next one.
"""

import re
from typing import Optional, TypedDict

CONTROL_CHARS = re.compile(r"[\x00-\x1F\x7F]")
RULE_HEADER = re.compile(r"^[•\-*→]?\s*([A-Z]{2,3})-(\d{2,4})\s*[—\-:]\s*(.+)$")
STARTS_WITH_BULLET_OR_DIGIT = re.compile(r"^[•\-*→\d]")
ENDS_WITH_PUNCTUATION = re.compile(r"[.!?:]$")
STARTS_WITH_CAPITAL = re.compile(r"^[A-Z]")


class Rule(TypedDict):
    code: str
    title: str
    section: Optional[str]
    text: str
    sort_order: int


def chunk(text: str) -> dict[str, list[Rule]]:
    lines = re.split(r"\r\n|\r|\n", text)

    grouped: dict[str, list[Rule]] = {}
    current_section: Optional[str] = None
    current: Optional[dict] = None
    previous_ended_with_colon = False

    def flush(entry: dict) -> None:
        prefix = entry["prefix"]
        grouped.setdefault(prefix, [])
        grouped[prefix].append(
            {
                "code": entry["code"],
                "title": entry["title"],
                "section": entry["section"],
                "text": "\n".join(entry["body"]).strip(),
                "sort_order": len(grouped[prefix]),
            }
        )

    for raw_line in lines:
        line = CONTROL_CHARS.sub(" ", raw_line).strip()

        if line == "":
            continue

        header_match = RULE_HEADER.match(line)
        if header_match:
            if current is not None:
                flush(current)

            current = {
                "prefix": header_match.group(1),
                "code": f"{header_match.group(1)}-{header_match.group(2)}",
                "title": header_match.group(3).strip(),
                "section": current_section,
                "body": [],
            }
            previous_ended_with_colon = False
            continue

        if not previous_ended_with_colon and _is_heading(line):
            current_section = line
            previous_ended_with_colon = False
            continue

        previous_ended_with_colon = line.endswith(":")

        if current is not None:
            current["body"].append(line.lstrip("•-*→\t "))

    if current is not None:
        flush(current)

    return grouped


def _is_heading(line: str) -> bool:
    if STARTS_WITH_BULLET_OR_DIGIT.match(line):
        return False

    if ENDS_WITH_PUNCTUATION.search(line):
        return False

    if not STARTS_WITH_CAPITAL.match(line):
        return False

    word_count = len(line.split())

    return 1 <= word_count <= 8
