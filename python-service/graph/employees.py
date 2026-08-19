import json

from database import get_mysql_connection


def get_employees_by_role(company_id: int, role: str) -> list[dict]:
    connection = get_mysql_connection()
    cursor = None

    try:
        cursor = connection.cursor(dictionary=True)

        cursor.execute(
            """
            SELECT id, name, role, skills, skill_level, active_project_count, status
            FROM employees
            WHERE company_id = %s
              AND role = %s
              AND status = 'active'
            ORDER BY active_project_count ASC
            """,
            (company_id, role),
        )

        rows = cursor.fetchall()

        for row in rows:
            if isinstance(row.get("skills"), str):
                row["skills"] = json.loads(row["skills"])

        return rows

    finally:
        if cursor:
            cursor.close()
        connection.close()
