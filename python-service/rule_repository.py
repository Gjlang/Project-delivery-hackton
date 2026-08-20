from database import get_mysql_connection


RULE_TABLES = {
    "business_rules",
    "employee_rules",
    "security_compliance",
    "technical_standards",
    "approval_governance",
    "testing_result_rules",
}


def validate_rule_table(category: str):
    if category not in RULE_TABLES:
        raise ValueError(
            f"Unsupported rule category: {category}"
        )


def count_rules_by_category(category: str, created_by: int) -> int:
    """Used to size a Qdrant retrieval limit so it always covers every
    active rule a user has in this category -- see graph/retrieval.py."""

    validate_rule_table(category)

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor()
        cursor.execute(
            f"SELECT COUNT(*) FROM {category} WHERE created_by = %s",
            (created_by,),
        )
        (count,) = cursor.fetchone()
        return count

    finally:
        if cursor:
            cursor.close()

        connection.close()


def get_rules_by_category(
    category: str,
    created_by: int,
) -> list[dict]:
    """Rules are per-user -- created_by is required, not an optional
    filter, so a query can never accidentally return another user's
    rulebook."""

    validate_rule_table(category)

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor(
            dictionary=True
        )

        query = f"""
            SELECT
                r.id,
                r.rule_code,
                r.section,
                r.title,
                r.rule_text,
                r.sort_order,
                r.source_document_id,
                r.created_by,
                r.created_at,
                r.updated_at,

                d.title AS document_title,
                d.version AS document_version,
                d.status AS document_status

            FROM {category} r

            INNER JOIN documents d
                ON d.id = r.source_document_id

            WHERE r.created_by = %s

            ORDER BY
                r.sort_order ASC,
                r.id ASC
        """

        cursor.execute(
            query,
            (created_by,)
        )

        return cursor.fetchall()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def get_rules_by_document(
    category: str,
    document_id: int,
) -> list[dict]:

    validate_rule_table(category)

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor(
            dictionary=True
        )

        query = f"""
            SELECT
                r.id,
                r.rule_code,
                r.section,
                r.title,
                r.rule_text,
                r.sort_order,
                r.source_document_id,
                r.created_by,
                r.created_at,
                r.updated_at,

                d.title AS document_title,
                d.version AS document_version,
                d.status AS document_status

            FROM {category} r

            INNER JOIN documents d
                ON d.id = r.source_document_id

            WHERE r.source_document_id = %s

            ORDER BY
                r.sort_order ASC,
                r.id ASC
        """

        cursor.execute(
            query,
            (document_id,)
        )

        return cursor.fetchall()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def get_rule_by_id(category: str, rule_id: int) -> dict | None:
    """LEFT JOIN, not INNER -- unlike get_rules_by_document(), this also
    has to work for rules added by hand through Rules Management, which
    have source_document_id = NULL and therefore no document row at all."""

    validate_rule_table(category)

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor(dictionary=True)

        query = f"""
            SELECT
                r.id,
                r.rule_code,
                r.section,
                r.title,
                r.rule_text,
                r.sort_order,
                r.source_document_id,
                r.created_by,
                r.created_at,
                r.updated_at,

                d.title AS document_title,
                d.version AS document_version,
                d.status AS document_status

            FROM {category} r

            LEFT JOIN documents d
                ON d.id = r.source_document_id

            WHERE r.id = %s
        """

        cursor.execute(query, (rule_id,))

        return cursor.fetchone()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def build_rule_json(
    rule: dict,
    category: str,
) -> dict:

    return {
        "rule_id": rule["id"],
        "rule_code": rule["rule_code"],
        "category": category,
        "section": rule["section"],
        "title": rule["title"],
        "rule_text": rule["rule_text"],
        "sort_order": rule["sort_order"],
        "owner_id": rule["created_by"],

        "source_document": {
            "id": rule["source_document_id"],
            "title": rule["document_title"],
            "version": rule["document_version"],
        },
    }


def build_embedding_text(
    rule_json: dict,
) -> str:

    return f"""
Category: {rule_json["category"]}

Section: {rule_json["section"]}

Rule Code: {rule_json["rule_code"]}

Title: {rule_json["title"]}

Rule:
{rule_json["rule_text"]}
""".strip()


# -- Writes -------------------------------------------------------------
#
# Added on top of the original read-only module so the FastAPI upload
# endpoint can own the whole parse -> store -> embed pipeline in Python.


def get_document(document_id: int) -> dict | None:
    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor(dictionary=True)

        cursor.execute(
            "SELECT id, category, path FROM documents WHERE id = %s",
            (document_id,),
        )

        return cursor.fetchone()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def delete_rules_by_document(table: str, document_id: int) -> None:
    validate_rule_table(table)

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor()

        cursor.execute(
            f"DELETE FROM {table} WHERE source_document_id = %s",
            (document_id,),
        )

        connection.commit()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def delete_document(document_id: int) -> None:
    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor()

        cursor.execute(
            "DELETE FROM documents WHERE id = %s",
            (document_id,),
        )

        connection.commit()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def create_document(
    category: str,
    title: str,
    original_filename: str,
    path: str,
    mime_type: str | None,
    size: int,
    created_by: int,
) -> int:

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor()

        cursor.execute(
            """
            INSERT INTO documents
                (category, title, original_filename, path, mime_type, size,
                 version, status, uploaded_by, created_at, updated_at)
            VALUES
                (%s, %s, %s, %s, %s, %s, '1.0', 'processing', %s, NOW(), NOW())
            """,
            (category, title, original_filename, path, mime_type, size, created_by),
        )

        connection.commit()

        return cursor.lastrowid

    finally:
        if cursor:
            cursor.close()

        connection.close()


def update_document(document_id: int, **fields) -> None:
    """
    Whitelisted partial update, e.g.
    update_document(1, status='indexed', extracted_sections=12, word_count=800, parsed_text='...')
    """

    allowed = {
        "status",
        "extracted_summary",
        "extracted_sections",
        "word_count",
        "parsed_text",
        "parse_error",
    }

    unknown = set(fields) - allowed
    if unknown:
        raise ValueError(f"Unsupported document field(s): {sorted(unknown)}")

    if not fields:
        return

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor()

        set_clause = ", ".join(f"{key} = %s" for key in fields)
        params = list(fields.values()) + [document_id]

        cursor.execute(
            f"UPDATE documents SET {set_clause}, updated_at = NOW() WHERE id = %s",
            params,
        )

        connection.commit()

    finally:
        if cursor:
            cursor.close()

        connection.close()


def upsert_rule(
    table: str,
    rule_code: str,
    section: str | None,
    title: str,
    rule_text: str,
    sort_order: int,
    source_document_id: int,
    created_by: int,
) -> int:
    """
    INSERT ... ON DUPLICATE KEY UPDATE, mirroring Laravel's
    updateOrCreate(['rule_code' => ...], [...]) -- rule_code is UNIQUE per
    (created_by, rule_code) now that rules are per-user, so re-uploading the
    same document updates that user's existing rows instead of duplicating
    them, and doesn't collide with another user's identically-coded rule.
    """

    validate_rule_table(table)

    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor()

        cursor.execute(
            f"""
            INSERT INTO {table}
                (created_by, rule_code, section, title, rule_text, sort_order, source_document_id, created_at, updated_at)
            VALUES
                (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                section = VALUES(section),
                title = VALUES(title),
                rule_text = VALUES(rule_text),
                sort_order = VALUES(sort_order),
                source_document_id = VALUES(source_document_id),
                updated_at = NOW()
            """,
            (created_by, rule_code, section, title, rule_text, sort_order, source_document_id),
        )

        connection.commit()

        return cursor.lastrowid

    finally:
        if cursor:
            cursor.close()

        connection.close()
