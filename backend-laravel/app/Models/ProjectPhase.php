<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPhase extends Model
{
    protected $fillable = [
        'project_id',
        'phase_number',
        'phase_name',
        'duration_days',
        'duration_reason',
        'tasks',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'tasks' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
