import os
import uuid

from dotenv import load_dotenv
from qdrant_client import QdrantClient
from qdrant_client.models import (
    Distance,
    FieldCondition,
    Filter,
    MatchValue,
    PointStruct,
    VectorParams,
)
from sentence_transformers import SentenceTransformer

from rule_repository import (
    RULE_TABLES,
    build_embedding_text,
    build_rule_json,
    get_rule_by_id,
    get_rules_by_category,
    get_rules_by_document,
)


load_dotenv()


EMBEDDING_MODEL_NAME = os.getenv(
    "EMBEDDING_MODEL",
    "sentence-transformers/all-MiniLM-L6-v2",
)

QDRANT_URL = os.getenv(
    "QDRANT_URL"
)

QDRANT_API_KEY = os.getenv(
    "QDRANT_API_KEY"
)

QDRANT_COLLECTION = os.getenv(
    "QDRANT_COLLECTION",
    "projectflow_rules",
)


embedding_model = SentenceTransformer(
    EMBEDDING_MODEL_NAME
)


qdrant_client = QdrantClient(
    url=QDRANT_URL,
    api_key=QDRANT_API_KEY,
)


def get_embedding_dimension() -> int:

    dimension = (
        embedding_model
        .get_sentence_embedding_dimension()
    )

    if dimension is None:
        raise RuntimeError(
            "Unable to determine embedding dimension"
        )

    return dimension


def ensure_collection_exists():

    if not qdrant_client.collection_exists(
        collection_name=QDRANT_COLLECTION
    ):
        qdrant_client.create_collection(
            collection_name=QDRANT_COLLECTION,

            vectors_config=VectorParams(
                size=get_embedding_dimension(),
                distance=Distance.COSINE,
            ),
        )

    ensure_payload_indexes()


def ensure_payload_indexes():
    """
    Qdrant Cloud requires an explicit payload index before a field can be
    used in a query filter -- the project-creation graph filters by
    `category` and `owner_id`. Idempotent: creating an index that already
    exists is a no-op error we swallow.
    """

    for field_name, field_schema in (
        ("category", "keyword"),
        ("owner_id", "integer"),
        ("source_document_id", "integer"),
    ):
        try:
            qdrant_client.create_payload_index(
                collection_name=QDRANT_COLLECTION,
                field_name=field_name,
                field_schema=field_schema,
            )
        except Exception:
            pass


def build_qdrant_point_id(
    category: str,
    mysql_rule_id: int,
) -> str:

    value = (
        f"projectflow:"
        f"{category}:"
        f"{mysql_rule_id}"
    )

    return str(
        uuid.uuid5(
            uuid.NAMESPACE_URL,
            value,
        )
    )


def build_qdrant_payload(
    rule_json: dict,
    embedding_text: str,
) -> dict:

    return {
        "mysql_rule_id":
            rule_json["rule_id"],

        "rule_code":
            rule_json["rule_code"],

        "category":
            rule_json["category"],

        "section":
            rule_json["section"],

        "title":
            rule_json["title"],

        "rule_text":
            rule_json["rule_text"],

        "owner_id":
            rule_json["owner_id"],

        "source_document_id":
            rule_json[
                "source_document"
            ]["id"],

        "document_title":
            rule_json[
                "source_document"
            ]["title"],

        "document_version":
            rule_json[
                "source_document"
            ]["version"],

        "embedding_model":
            EMBEDDING_MODEL_NAME,

        "embedding_text":
            embedding_text,
    }


def embed_texts(
    texts: list[str],
) -> list[list[float]]:

    vectors = embedding_model.encode(
        texts,
        batch_size=32,
        normalize_embeddings=True,
    )

    return vectors.tolist()


def index_rules(
    rules: list[dict],
    category: str,
) -> int:

    if not rules:
        return 0

    ensure_collection_exists()

    prepared_rules = []

    for rule in rules:

        rule_json = build_rule_json(
            rule=rule,
            category=category,
        )

        embedding_text = build_embedding_text(
            rule_json=rule_json
        )

        prepared_rules.append(
            {
                "json": rule_json,
                "embedding_text":
                    embedding_text,
            }
        )

    texts = [
        item["embedding_text"]
        for item in prepared_rules
    ]

    vectors = embed_texts(
        texts
    )

    points = []

    for item, vector in zip(
        prepared_rules,
        vectors,
    ):

        rule_json = item["json"]

        point_id = build_qdrant_point_id(
            category=category,
            mysql_rule_id=rule_json[
                "rule_id"
            ],
        )

        payload = build_qdrant_payload(
            rule_json=rule_json,
            embedding_text=item[
                "embedding_text"
            ],
        )

        points.append(
            PointStruct(
                id=point_id,
                vector=vector,
                payload=payload,
            )
        )

    qdrant_client.upsert(
        collection_name=QDRANT_COLLECTION,
        points=points,
        wait=True,
    )

    return len(points)


def index_document(
    category: str,
    document_id: int,
) -> int:

    rules = get_rules_by_document(
        category=category,
        document_id=document_id,
    )

    return index_rules(
        rules=rules,
        category=category,
    )


def index_category(
    category: str,
    created_by: int,
) -> int:

    rules = get_rules_by_category(
        category=category,
        created_by=created_by,
    )

    return index_rules(
        rules=rules,
        category=category,
    )


def index_all_categories(
    created_by: int,
) -> dict:

    results = {}

    for category in RULE_TABLES:

        results[category] = (
            index_category(
                category=category,
                created_by=created_by,
            )
        )

    return results


def delete_by_document(document_id: int) -> None:

    if not qdrant_client.collection_exists(
        collection_name=QDRANT_COLLECTION
    ):
        return

    qdrant_client.delete(
        collection_name=QDRANT_COLLECTION,
        points_selector=Filter(
            must=[
                FieldCondition(
                    key="source_document_id",
                    match=MatchValue(value=document_id),
                )
            ]
        ),
        wait=True,
    )


def index_rule_by_id(category: str, rule_id: int) -> int:
    """Used by RuleController's manual create/update in Laravel -- a
    single rule (possibly with no source document at all) gets embedded
    and upserted into Qdrant the same way a PDF-extracted rule would be."""

    rule = get_rule_by_id(category, rule_id)

    if rule is None:
        return 0

    return index_rules(rules=[rule], category=category)


def delete_rule_by_id(category: str, rule_id: int) -> None:
    """Companion to index_rule_by_id() -- used on delete. Computes the same
    deterministic point id build_qdrant_point_id() would have assigned it,
    so no lookup by source_document_id is needed (manual rules don't have
    one, and filtering by it would risk matching other manual rules that
    also have source_document_id = NULL)."""

    if not qdrant_client.collection_exists(collection_name=QDRANT_COLLECTION):
        return

    point_id = build_qdrant_point_id(category=category, mysql_rule_id=rule_id)

    qdrant_client.delete(
        collection_name=QDRANT_COLLECTION,
        points_selector=[point_id],
        wait=True,
    )
