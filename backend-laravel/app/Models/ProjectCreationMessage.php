<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCreationMessage extends Model
{
    protected $fillable = [
        'session_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(ProjectCreationSession::class, 'session_id');
    }
}
