<?php

return [

    'url' => env('QDRANT_URL', 'http://localhost:6333'),
    'api_key' => env('QDRANT_API_KEY'),

    // One shared collection for all companies' rule chunks -- company/category
    // isolation is enforced via payload filters on every search, not via
    // separate collections.
    'collection' => env('QDRANT_COLLECTION', 'projectflow_company_rules'),

    // Must match the embedding provider's output dimensionality.
    'vector_size' => (int) env('QDRANT_VECTOR_SIZE', 768),
    'distance' => env('QDRANT_DISTANCE', 'Cosine'),

];
