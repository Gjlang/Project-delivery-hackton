<?php

return [

    'categories' => [
        'business_rules' => [
            'label' => 'Business Rules',
            'description' => 'Customer-process, Product rules, Core workflows',
            'color' => 'orange',
            'keywords' => [
                'workflow', 'process', 'customer', 'product rule', 'core rule', 'business rule',
                'use case', 'exception', 'edge case', 'validation', 'eligibility', 'condition',
            ],
        ],
        'company_policies' => [
            'label' => 'Company Rules and Policies',
            'description' => 'Procurement, Communication, Legal standards',
            'color' => 'blue',
            'keywords' => [
                'policy', 'procurement', 'vendor', 'communication', 'legal', 'code of conduct',
                'confidentiality', 'nda', 'disciplinary', 'compliance policy', 'standard operating procedure',
            ],
        ],
        'employee_rules' => [
            'label' => 'Employee and Working Rules',
            'description' => 'Max workload, Overtime, Remote work protocols',
            'color' => 'amber',
            'keywords' => [
                'workload', 'overtime', 'remote work', 'work from home', 'leave', 'pto',
                'working hours', 'shift', 'attendance', 'onboarding', 'probation',
            ],
        ],
        'security_compliance' => [
            'label' => 'Security and Compliance',
            'description' => 'Data privacy, GDPR, Internal auditing requirements',
            'color' => 'red',
            'keywords' => [
                'gdpr', 'data privacy', 'encryption', 'audit', 'access control', 'authentication',
                'incident response', 'retention', 'pii', 'breach', 'compliance', 'iso 27001',
            ],
        ],
        'technical_standards' => [
            'label' => 'Technical Standards',
            'description' => 'Frameworks, Coding conventions, API specs',
            'color' => 'slate',
            'keywords' => [
                'framework', 'coding convention', 'api', 'endpoint', 'architecture', 'code review',
                'style guide', 'versioning', 'deployment', 'testing standard', 'ci/cd', 'schema',
            ],
        ],
        'approval_governance' => [
            'label' => 'Approval and Governance',
            'description' => 'Budget approval, Escalation paths, Stakeholders',
            'color' => 'purple',
            'keywords' => [
                'approval', 'budget', 'escalation', 'stakeholder', 'sign-off', 'signature',
                'governance', 'authorization', 'threshold', 'committee', 'sponsor', 'steering',
            ],
        ],
    ],

    // Words that signal a sentence states an actual rule (rather than being
    // narrative text). Matched word-by-word against every sentence, regardless
    // of category, so a "found rule" can surface even in an unfamiliar category.
    'rule_trigger_words' => [
        'must', 'shall', 'require', 'requires', 'required', 'requiring',
        'mandatory', 'forbidden', 'prohibited', 'not allowed', 'not permitted',
        'minimum', 'maximum', 'at least', 'no more than', 'no later than',
        'within', 'deadline', 'threshold', 'limit', 'cap', 'quota',
        'approval', 'approve', 'authorized', 'authorization', 'sign-off',
        'escalate', 'escalation', 'bypass', 'exempt', 'exception',
        'shall not', 'may not', 'need to', 'has to', 'have to',
    ],

];
