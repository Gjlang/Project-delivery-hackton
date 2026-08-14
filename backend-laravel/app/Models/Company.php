<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Company $company) {
            $company->uuid ??= (string) Str::uuid();
            $company->status ??= 'active';
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
