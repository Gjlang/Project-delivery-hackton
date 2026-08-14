<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleCondition extends Model
{
    protected $fillable = [
        'company_rule_id',
        'field',
        'operator',
        'value',
        'value_type',
        'condition_group',
        'logical_operator',
        'description',
        'sort_order',
    ];

    public function companyRule()
    {
        return $this->belongsTo(CompanyRule::class);
    }
}
