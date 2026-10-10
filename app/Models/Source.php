<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'url',
        'publisher',
        'published_at',
        'raw_content',
        'content_hash',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($source) {
            if (empty($source->uuid)) {
                $source->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
