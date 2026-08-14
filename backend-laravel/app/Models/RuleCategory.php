<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function companyRules()
    {
        return $this->hasMany(CompanyRule::class);
    }
}
