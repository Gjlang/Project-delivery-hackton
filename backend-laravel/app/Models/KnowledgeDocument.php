<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeDocument extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'version',
        'status',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function companyRules()
    {
        return $this->hasMany(CompanyRule::class, 'source_document_id');
    }
}
