import os
import uuid

from dotenv import load_dotenv
from qdrant_client import QdrantClient
from qdrant_client.models import (
    Distance,
    PointStruct,
    VectorParams,
)
from sentence_transformers import SentenceTransformer

from rule_repository import (
    RULE_TABLES,
    build_embedding_text,
    build_rule_json,
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

    if qdrant_client.collection_exists(
        collection_name=QDRANT_COLLECTION
    ):
        return

    qdrant_client.create_collection(
        collection_name=QDRANT_COLLECTION,

        vectors_config=VectorParams(
            size=get_embedding_dimension(),
            distance=Distance.COSINE,
        ),
    )


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

        "company_id":
            rule_json["company_id"],

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
    company_id: int | None = None,
) -> int:

    rules = get_rules_by_category(
        category=category,
        company_id=company_id,
    )

    return index_rules(
        rules=rules,
        category=category,
    )


def index_all_categories(
    company_id: int | None = None,
) -> dict:

    results = {}

    for category in RULE_TABLES:

        results[category] = (
            index_category(
                category=category,
                company_id=company_id,
            )
        )

    return results
