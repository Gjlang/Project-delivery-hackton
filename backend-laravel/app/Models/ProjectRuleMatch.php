<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRuleMatch extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'company_rule_id',
        'context',
        'decision',
        'similarity_score',
        'reason',
        'source_reference',
    ];

    protected $casts = [
        'similarity_score' => 'float',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function companyRule()
    {
        return $this->belongsTo(CompanyRule::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
