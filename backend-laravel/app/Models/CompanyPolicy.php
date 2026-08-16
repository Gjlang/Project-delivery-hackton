<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPolicy extends Model
{
    protected $table = 'company_policies';

    protected $fillable = [
        'rule_code',
        'section',
        'title',
        'rule_text',
        'sort_order',
        'source_document_id',
    ];

    public function sourceDocument()
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }
}
