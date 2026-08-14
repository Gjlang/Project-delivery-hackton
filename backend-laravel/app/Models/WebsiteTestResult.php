<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteTestResult extends Model
{
    protected $fillable = [
        'website_test_run_id',
        'company_rule_id',
        'rule_code',
        'category',
        'applicability_status',
        'status',
        'tested_page',
        'expected_behavior',
        'observed_behavior',
        'severity',
        'similarity_score',
        'execution_duration_ms',
        'evidence',
        'ai_explanation',
        'ai_impact',
        'ai_recommendation',
    ];

    protected $casts = [
        'evidence' => 'array',
        'similarity_score' => 'float',
    ];

    public function run()
    {
        return $this->belongsTo(WebsiteTestRun::class, 'website_test_run_id');
    }

    public function companyRule()
    {
        return $this->belongsTo(CompanyRule::class);
    }
}
