<?php

use App\Models\ApprovalGovernanceRule;
use App\Models\BusinessRule;
use App\Models\EmployeeRule;
use App\Models\SecurityComplianceRule;
use App\Models\TechnicalStandard;
use App\Models\TestingResultRule;

return [
    'BR' => [
        'key' => 'business_rules',
        'label' => 'Business Rules',
        'color' => 'orange',
        'model' => BusinessRule::class,
    ],
    'EW' => [
        'key' => 'employee_rules',
        'label' => 'Employee and Working Rules',
        'color' => 'amber',
        'model' => EmployeeRule::class,
    ],
    'SC' => [
        'key' => 'security_compliance',
        'label' => 'Security and Compliance',
        'color' => 'red',
        'model' => SecurityComplianceRule::class,
    ],
    'TS' => [
        'key' => 'technical_standards',
        'label' => 'Technical Standards',
        'color' => 'slate',
        'model' => TechnicalStandard::class,
    ],
    'AG' => [
        'key' => 'approval_governance',
        'label' => 'Approval and Governance',
        'color' => 'purple',
        'model' => ApprovalGovernanceRule::class,
    ],
    'TR' => [
        'key' => 'testing_result_rules',
        'label' => 'Testing Standards',
        'color' => 'emerald',
        'model' => TestingResultRule::class,
    ],
];
