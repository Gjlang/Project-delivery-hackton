<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'title',
        'original_filename',
        'path',
        'mime_type',
        'size',
        'version',
        'status',
        'extracted_summary',
        'extracted_sections',
        'extracted_rules',
        'keyword_hits',
        'word_count',
        'parsed_text',
        'parse_error',
        'uploaded_by',
    ];

    protected $casts = [
        'extracted_rules' => 'array',
        'keyword_hits' => 'array',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
