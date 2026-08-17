"""
Text extraction, ported from backend-laravel/app/Services/DocumentParser.php.
PDF/DOCX use real parsers (pypdf/python-docx) rather than the PHP version's
manual zip/XML approach -- simpler and more robust for the same result.
"""

import re

from docx import Document as DocxDocument
from pypdf import PdfReader

PRINTABLE_RUN = re.compile(rb"[\x20-\x7E]{5,}")


def extract_text(absolute_path: str, extension: str) -> str:
    extension = extension.lower()

    if extension == "pdf":
        text = _extract_from_pdf(absolute_path)
    elif extension == "docx":
        text = _extract_from_docx(absolute_path)
    elif extension == "doc":
        text = _extract_printable_strings(absolute_path)
    elif extension in ("txt", "md"):
        with open(absolute_path, "r", encoding="utf-8", errors="ignore") as f:
            text = f.read()
    else:
        text = ""

    return text.strip()


def _extract_from_pdf(absolute_path: str) -> str:
    reader = PdfReader(absolute_path)
    pages = [page.extract_text() or "" for page in reader.pages]
    return "\n".join(pages)


def _extract_from_docx(absolute_path: str) -> str:
    doc = DocxDocument(absolute_path)
    return "\n".join(paragraph.text for paragraph in doc.paragraphs)


def _extract_printable_strings(absolute_path: str) -> str:
    """
    Best-effort text recovery for legacy binary .doc files (no reliable
    parser without a heavy external dependency): pulls out runs of printable
    characters, which is enough to rule-code/keyword match against.
    """
    with open(absolute_path, "rb") as f:
        raw = f.read()

    matches = PRINTABLE_RUN.findall(raw)
    return "\n".join(m.decode("ascii", errors="ignore") for m in matches)
