<?php

namespace App\Models;

use App\Enums\LlmJobStatus;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $title
 * @property string|null $url
 * @property string|null $publisher
 * @property Carbon|null $published_at
 * @property string $raw_content
 * @property string $content_hash
 * @property int|null $created_by
 * @property array|null $extraction_meta
 * @property LlmJobStatus $extraction_status
 * @property string|null $extraction_error
 */
class Source extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'title',
        'url',
        'publisher',
        'published_at',
        'raw_content',
        'content_hash',
        'created_by',
        'extraction_meta',
        'extraction_status',
        'extraction_error',
    ];

    protected $casts = [
        'published_at' => 'date',
        'extraction_meta' => 'array',
        'extraction_status' => LlmJobStatus::class,
    ];

    protected static function booted()
    {
        static::creating(function (Source $source) {
            if (empty($source->uuid)) {
                $source->uuid = (string) Uuid::uuid4();
            }
            if (empty($source->content_hash) && ! empty($source->raw_content)) {
                $source->content_hash = hash('sha256', $source->raw_content);
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
