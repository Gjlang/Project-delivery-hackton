"""
Qdrant retrieval for the project-creation graph. Reuses the existing
embedding model + Qdrant client singletons from qdrant_indexer.py instead of
instantiating a second copy -- same collection, same payload shape already
written by the Company Rules upload pipeline.
"""

from qdrant_client.models import FieldCondition, Filter, MatchValue

from qdrant_indexer import QDRANT_COLLECTION, embedding_model, ensure_payload_indexes, qdrant_client

# Qdrant Cloud requires an explicit index before `category`/`company_id` can
# be used in a query filter -- ensure it once at import time.
ensure_payload_indexes()


def _embed(text: str) -> list[float]:
    vector = embedding_model.encode(text, normalize_embeddings=True)
    return vector.tolist()


def retrieve_business_rules(
    queries: list[str],
    company_id: int | None = None,
    limit_per_query: int = 4,
) -> list[dict]:
    # The company rulebook is global app-wide (see
    # CompanyRuleReadinessService's own comment on this), so every indexed
    # rule point has payload company_id: null. Filtering on a real
    # company_id here would silently match zero rules every time -- the
    # `company_id` parameter is kept for a future multi-tenant rulebook but
    # is intentionally NOT applied as a Qdrant filter yet.
    found: dict[str, dict] = {}

    for query in queries:
        must = [FieldCondition(key="category", match=MatchValue(value="business_rules"))]

        result = qdrant_client.query_points(
            collection_name=QDRANT_COLLECTION,
            query=_embed(query),
            query_filter=Filter(must=must),
            limit=limit_per_query,
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
    company_id: int | None = None,
    limit: int = 6,
) -> list[dict]:
    # See retrieve_business_rules() -- company_id is intentionally not
    # applied as a filter; the rulebook is global, not per-company.
    must = [FieldCondition(key="category", match=MatchValue(value="employee_rules"))]

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
