<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRuleMatch extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'rule_type',
        'rule_id',
        'rule_code',
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

    /**
     * The matched rule row lives in one of several per-category tables (see
     * config/knowledge_rules.php), keyed by rule_type -- there's no single
     * model to eager-load generically, so callers needing the full rule
     * should resolve it via config('knowledge_rules')[$match->rule_type]['model'].
     */
    public function rule(): ?Model
    {
        $model = config("knowledge_rules.{$this->rule_type}.model");

        return $model ? $model::find($this->rule_id) : null;
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
