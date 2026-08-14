<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'business_objective',
        'requirements_raw',
        'start_date',
        'status',
        'requirement_analysis_status',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function requirements()
    {
        return $this->hasMany(ProjectRequirement::class);
    }

    public function analysisRuns()
    {
        return $this->hasMany(RequirementAnalysisRun::class);
    }

    public function latestAnalysisRun()
    {
        return $this->hasOne(RequirementAnalysisRun::class)->latestOfMany();
    }

    public function testRuns()
    {
        return $this->hasMany(WebsiteTestRun::class);
    }

    public function latestTestRun()
    {
        return $this->hasOne(WebsiteTestRun::class)->latestOfMany();
    }
}
