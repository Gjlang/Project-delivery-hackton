"""
Qdrant retrieval for the project-creation graph. Reuses the existing
embedding model + Qdrant client singletons from qdrant_indexer.py instead of
instantiating a second copy -- same collection, same payload shape already
written by the Company Rules upload pipeline.

Both business and employee rules use a per-query limit sized to the user's
total active rule count in that category (rule_repository.count_rules_by_category),
not a fixed top-k -- real semantic retrieval, but a rule can never silently
drop out of results just for scoring low against the generated queries,
since the ceiling is the whole set.
"""

from qdrant_client.models import FieldCondition, Filter, MatchValue

from qdrant_indexer import QDRANT_COLLECTION, embedding_model, ensure_payload_indexes, qdrant_client
from rule_repository import count_rules_by_category

# Qdrant Cloud requires an explicit index before `category`/`owner_id` can
# be used in a query filter -- ensure it once at import time.
ensure_payload_indexes()


def _embed(text: str) -> list[float]:
    vector = embedding_model.encode(text, normalize_embeddings=True)
    return vector.tolist()


def retrieve_business_rules(
    queries: list[str],
    owner_id: int,
    limit_per_query: int | None = None,
) -> list[dict]:
    total = count_rules_by_category("business_rules", created_by=owner_id)
    if total == 0 or not queries:
        return []

    limit = limit_per_query or total

    must = [
        FieldCondition(key="category", match=MatchValue(value="business_rules")),
        FieldCondition(key="owner_id", match=MatchValue(value=owner_id)),
    ]

    found: dict[str, dict] = {}

    for query in queries:
        result = qdrant_client.query_points(
            collection_name=QDRANT_COLLECTION,
            query=_embed(query),
            query_filter=Filter(must=must),
            limit=limit,
            with_payload=True,
        )

        for point in result.points:
            payload = point.payload
            code = payload["rule_code"]
            existing = found.get(code)

            if existing is None or point.score > existing["score"]:
                found[code] = {
                    "score": float(point.score),
                    "rule_code": code,
                    "rule_id": payload.get("mysql_rule_id"),
                    "category": payload.get("category", "business_rules"),
                    "title": payload.get("title"),
                    "section": payload.get("section"),
                    "rule_text": payload.get("rule_text"),
                }

    return sorted(found.values(), key=lambda x: x["score"], reverse=True)


def retrieve_employee_rules(
    query: str,
    owner_id: int,
    limit: int | None = None,
) -> list[dict]:
    total = count_rules_by_category("employee_rules", created_by=owner_id)
    if total == 0:
        return []

    limit = limit or total

    must = [
        FieldCondition(key="category", match=MatchValue(value="employee_rules")),
        FieldCondition(key="owner_id", match=MatchValue(value=owner_id)),
    ]

    result = qdrant_client.query_points(
        collection_name=QDRANT_COLLECTION,
        query=_embed(query),
        query_filter=Filter(must=must),
        limit=limit,
        with_payload=True,
    )

    return [
        {
            "score": float(point.score),
            "rule_code": point.payload.get("rule_code"),
            "rule_text": point.payload.get("rule_text"),
        }
        for point in result.points
    ]
