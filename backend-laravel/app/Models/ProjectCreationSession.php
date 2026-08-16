<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCreationSession extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'analysis_status',
        'rules_status',
        'draft_data',
        'decision_progress',
        'clarifications',
        'confirmed_project_id',
        'confirmed_at',
    ];

    protected $casts = [
        'draft_data' => 'array',
        'decision_progress' => 'array',
        'clarifications' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(ProjectCreationMessage::class, 'session_id')->orderBy('created_at');
    }

    public function confirmedProject()
    {
        return $this->belongsTo(Project::class, 'confirmed_project_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
