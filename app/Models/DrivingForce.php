<?php

namespace App\Models;

use App\Http\Resources\ApprovalItemsCollection;
use App\Http\Resources\ClosedItemsCollection;
use App\Http\Resources\ClosedItemsResource;
use App\Http\Resources\DrivingForceCollection;
use App\Http\Resources\DrivingForceResource;
use App\Http\Resources\RatingUrgencyCollection;
use App\Http\Resources\StatusActionCollection;
use App\Http\Resources\TimeHorizonCollection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrivingForce extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'dimension_id',
        'pic',
        'created_by',
        'updated_by',
        'keyword',
        'description',
        'status',
        'remark',
        'approved_at',
        'closed_at'
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_id');
    }

    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pic_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic');
    }

    public function updated_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(DrivingForceRating::class);
    }

    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->translatedFormat('D, d F Y')
        );
    }

    public function approvedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->translatedFormat('d F Y') : null
        );
    }

    public function closedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->translatedFormat('D, d F Y') : null
        );
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
