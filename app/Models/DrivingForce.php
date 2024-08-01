<?php

namespace App\Models;

use App\Http\Resources\DrivingForceCollection;
use App\Http\Resources\DrivingForceResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrivingForce extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'dimension_id',
        'created_by',
        'updated_by',
        'keyword',
        'description',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_id');
    }

    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updated_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(DrivingForceRating::class);
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
            get: fn ($value) => $value ? Carbon::parse($value)->translatedFormat('D, d F Y') : null
        );
    }

    public static function filter()
    {
        $pagination = request('pagination.pageSize');
        $search = request('search');
        $dimension = request('dimension');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $driving_forces = self::with(['dimension', 'created_by_user', 'updated_by_user'])
            ->when($search, function ($q) use ($search) {
                $q->where('keyword', 'LIKE', '%' . $search . '%')
                    ->where('description', 'LIKE', '%' . $search . '%');
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $date_start)
            ->where('created_at', '<=', $date_end)
            ->paginate($pagination);


        return new DrivingForceCollection($driving_forces);
    }
}
