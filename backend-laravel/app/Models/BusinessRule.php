<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRule extends Model
{
    protected $table = 'business_rules';

    protected $fillable = [
        'created_by',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
