<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRequirement extends Model
{
    protected $fillable = [
        'project_id',
        'requirement_type',
        'name',
        'description',
        'source_text',
        'priority',
        'is_mandatory',
        'status',
        'clarification_reason',
        'clarification_question',
        'metadata',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'metadata' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
