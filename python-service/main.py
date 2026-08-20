import os
import uuid
from pathlib import Path

from dotenv import load_dotenv
from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse

import config
import document_parser
import qdrant_indexer
import rule_chunker
import rule_repository
import threads

load_dotenv()

API_KEY = os.getenv("API_KEY")

STORAGE_DIR = Path(__file__).parent / "storage" / "documents"
STORAGE_DIR.mkdir(parents=True, exist_ok=True)

ALLOWED_EXTENSIONS = {"pdf", "doc", "docx", "txt", "md"}
MAX_UPLOAD_BYTES = 20 * 1024 * 1024  # 20 MB, matches Laravel's existing limit

app = FastAPI(title="ProjectFlow Knowledge Indexer")


def _check_api_key(x_api_key: str | None) -> None:
    if not API_KEY:
        # No key configured yet -- don't lock the developer out during setup,
        # but this should be set before the service is reachable on a
        # network Laravel isn't the only thing on.
        return

    if x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="Invalid or missing X-API-Key header.")


@app.middleware("http")
async def require_api_key_for_threads(request, call_next):
    if request.url.path.startswith("/threads"):
        try:
            _check_api_key(request.headers.get("x-api-key"))
        except HTTPException as e:
            return JSONResponse(status_code=e.status_code, content={"detail": e.detail})
    return await call_next(request)


app.include_router(threads.router)


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/documents/upload")
async def upload_document(
    file: UploadFile = File(...),
    category: str = Form(...),
    created_by: int = Form(...),
    x_api_key: str | None = Header(default=None, alias="X-API-Key"),
):
    _check_api_key(x_api_key)

    try:
        table = config.table_for_prefix(category)
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))

    extension = (file.filename or "").rsplit(".", 1)[-1].lower() if "." in (file.filename or "") else ""
    if extension not in ALLOWED_EXTENSIONS:
        raise HTTPException(status_code=422, detail=f"Unsupported file type: .{extension}")

    body = await file.read()
    if len(body) > MAX_UPLOAD_BYTES:
        raise HTTPException(status_code=422, detail="File exceeds the 20MB upload limit.")

    stored_filename = f"{uuid.uuid4().hex}_{file.filename}"
    stored_path = STORAGE_DIR / stored_filename
    stored_path.write_bytes(body)

    title = Path(file.filename or stored_filename).stem

    document_id = rule_repository.create_document(
        category=category,
        title=title,
        original_filename=file.filename or stored_filename,
        path=str(stored_path),
        mime_type=file.content_type,
        size=len(body),
        created_by=created_by,
    )

    try:
        text = document_parser.extract_text(str(stored_path), extension)
        grouped = rule_chunker.chunk(text)
        rules_for_category = grouped.get(category, [])

        for rule in rules_for_category:
            rule_repository.upsert_rule(
                table=table,
                rule_code=rule["code"],
                section=rule["section"],
                title=rule["title"],
                rule_text=rule["text"],
                sort_order=rule["sort_order"],
                source_document_id=document_id,
                created_by=created_by,
            )

        rule_repository.update_document(
            document_id,
            status="indexed",
            extracted_sections=len(rules_for_category),
            word_count=len(text.split()),
            parsed_text=text,
        )

        rules_for_qdrant = rule_repository.get_rules_by_document(table, document_id)
        qdrant_indexer.index_rules(rules_for_qdrant, table)

        return {
            "status": "success",
            "document_id": document_id,
            "category": category,
            "filename": file.filename,
            "total_rules": len(rules_for_category),
        }

    except Exception as e:
        rule_repository.update_document(document_id, status="error", parse_error=str(e))

        # HTTP 200 on purpose: the existing Laravel/JS contract branches on
        # the JSON body's "status" field, not the HTTP status code.
        return JSONResponse(
            status_code=200,
            content={"status": "error", "document_id": document_id, "error": str(e)},
        )


@app.delete("/documents/{document_id}")
def delete_document(
    document_id: int,
    x_api_key: str | None = Header(default=None, alias="X-API-Key"),
):
    _check_api_key(x_api_key)

    document = rule_repository.get_document(document_id)
    if document is None:
        raise HTTPException(status_code=404, detail="Document not found.")

    try:
        table = config.table_for_prefix(document["category"])
        rule_repository.delete_rules_by_document(table, document_id)
    except ValueError:
        # Category no longer maps to a known rule table -- nothing to clean
        # up in MySQL rule tables, still proceed with Qdrant + file + row.
        pass

    qdrant_indexer.delete_by_document(document_id)

    path = document.get("path")
    if path:
        Path(path).unlink(missing_ok=True)

    rule_repository.delete_document(document_id)

    return {"status": "deleted", "document_id": document_id}


@app.post("/rules/{category}/{rule_id}/index")
def index_rule(
    category: str,
    rule_id: int,
    x_api_key: str | None = Header(default=None, alias="X-API-Key"),
):
    """Called by RuleController (Laravel) after a manual create/update in
    Rules Management, so the rule is embedded and searchable the same way
    a PDF-uploaded one is -- without this, a hand-typed rule would exist in
    MySQL but never be found by the AI chat's Qdrant retrieval."""
    _check_api_key(x_api_key)

    try:
        table = config.table_for_prefix(category)
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))

    count = qdrant_indexer.index_rule_by_id(table, rule_id)

    if count == 0:
        raise HTTPException(status_code=404, detail="Rule not found.")

    return {"status": "indexed", "rule_id": rule_id}


@app.delete("/rules/{category}/{rule_id}/index")
def delete_rule_index(
    category: str,
    rule_id: int,
    x_api_key: str | None = Header(default=None, alias="X-API-Key"),
):
    _check_api_key(x_api_key)

    try:
        table = config.table_for_prefix(category)
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))

    qdrant_indexer.delete_rule_by_id(table, rule_id)

    return {"status": "deleted", "rule_id": rule_id}
