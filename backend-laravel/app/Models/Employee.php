<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'role',
        'skills',
        'skill_level',
        'active_project_count',
        'status',
    ];

    protected $casts = [
        'skills' => 'array',
        'active_project_count' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
