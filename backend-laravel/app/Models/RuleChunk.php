<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleChunk extends Model
{
    protected $fillable = [
        'company_rule_id',
        'chunk_index',
        'chunk_text',
        'token_count',
        'embedding_status',
        'external_vector_id',
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
