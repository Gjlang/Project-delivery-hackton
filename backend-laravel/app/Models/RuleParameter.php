<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleParameter extends Model
{
    protected $fillable = [
        'company_rule_id',
        'parameter_key',
        'parameter_value',
        'value_type',
        'unit',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function companyRule()
    {
        return $this->belongsTo(CompanyRule::class);
    }
}
