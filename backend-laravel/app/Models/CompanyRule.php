<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'rule_category_id',
        'source_document_id',
        'supersedes_rule_id',
        'rule_code',
        'title',
        'rule_text',
        'section',
        'subsection',
        'applicable_condition',
        'required_behavior',
        'expected_outcome',
        'rule_type',
        'evaluation_type',
        'severity',
        'priority',
        'is_mandatory',
        'is_active',
        'version',
        'status',
        'effective_from',
        'effective_until',
        'metadata',
        'source_reference',
        'source_page',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'metadata' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(RuleCategory::class, 'rule_category_id');
    }

    public function sourceDocument()
    {
        return $this->belongsTo(KnowledgeDocument::class, 'source_document_id');
    }

    public function supersedesRule()
    {
        return $this->belongsTo(CompanyRule::class, 'supersedes_rule_id');
    }

    public function supersededBy()
    {
        return $this->hasOne(CompanyRule::class, 'supersedes_rule_id');
    }

    public function conditions()
    {
        return $this->hasMany(RuleCondition::class)->orderBy('sort_order');
    }

    public function parameters()
    {
        return $this->hasMany(RuleParameter::class);
    }

    public function chunks()
    {
        return $this->hasMany(RuleChunk::class)->orderBy('chunk_index');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
