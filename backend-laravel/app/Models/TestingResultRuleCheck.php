<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestingResultRuleCheck extends Model
{
    protected $fillable = [
        'website_test_run_id',
        'rule_id',
        'rule_code',
        'title',
        'status',
        'reason',
    ];

    public function run()
    {
        return $this->belongsTo(WebsiteTestRun::class, 'website_test_run_id');
    }
}
