<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementAnalysisRun extends Model
{
    protected $fillable = [
        'project_id',
        'status',
        'model_provider',
        'model_name',
        'raw_input_snapshot',
        'structured_output',
        'relevant_rules',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'raw_input_snapshot' => 'array',
        'structured_output' => 'array',
        'relevant_rules' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
