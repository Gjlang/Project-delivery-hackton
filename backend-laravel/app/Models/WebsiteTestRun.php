<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteTestRun extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'website_url',
        'status',
        'browser_configuration',
        'test_context_snapshot',
        'started_at',
        'completed_at',
        'total_rules',
        'executed_tests',
        'passed',
        'warnings',
        'failed',
        'not_testable',
        'created_by',
    ];

    protected $casts = [
        'browser_configuration' => 'array',
        'test_context_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function results()
    {
        return $this->hasMany(WebsiteTestResult::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
