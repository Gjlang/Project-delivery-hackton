<?php

use App\Models\ApprovalGovernanceRule;
use App\Models\BusinessRule;
use App\Models\CompanyPolicy;
use App\Models\EmployeeRule;
use App\Models\SecurityComplianceRule;
use App\Models\TechnicalStandard;

return [
    'BR' => [
        'key' => 'business_rules',
        'label' => 'Business Rules',
        'color' => 'orange',
        'model' => BusinessRule::class,
    ],
    'CP' => [
        'key' => 'company_policies',
        'label' => 'Company Rules and Policies',
        'color' => 'blue',
        'model' => CompanyPolicy::class,
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
];
