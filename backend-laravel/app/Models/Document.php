<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
