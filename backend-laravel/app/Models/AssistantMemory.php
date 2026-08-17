<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantMemory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'assistant_thread_id',
        'content',
        'source',
    ];

    public function thread()
    {
        return $this->belongsTo(AssistantThread::class, 'assistant_thread_id');
    }
}
