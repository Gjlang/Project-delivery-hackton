<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantThread extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function memories()
    {
        return $this->hasMany(AssistantMemory::class)->orderBy('created_at');
    }
}
