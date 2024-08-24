<?php

namespace App\Models;

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
        'remark'
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

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public static function filter()
    {
        $pagination = request('pagination.pageSize');
        $search = request('search');
        $dimension = request('dimension');
        $status = request('status');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $driving_forces = self::with(['dimension', 'created_by_user', 'updated_by_user'])
            ->when($search, function ($q) use ($search) {
                $q->where('keyword', 'LIKE',  $search . '%')
                    ->orWhere('description', 'LIKE',  $search . '%');
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->where('created_at', '>=', $date_start)
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'DESC')
            ->paginate($pagination);


        return new DrivingForceCollection($driving_forces);
    }

    public static function time_horizon()
    {
        $pagination = request('pagination.pageSize');
        $search = request('search');
        $dimension = request('dimension');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $time_horizons = self::with(['dimension', 'rating'])
            ->when($search, function ($q) use ($search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $date_start)
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'DESC')
            ->paginate($pagination);

        return new TimeHorizonCollection($time_horizons);
    }

    public static function rating_urgency()
    {
        $pagination = request('pagination.pageSize');
        $search = request('search');
        $dimension = request('dimension');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $time_horizons = self::with(['dimension', 'rating'])
            ->when($search, function ($q) use ($search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $date_start)
            ->has('rating')
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'DESC')
            ->paginate($pagination);

        return new RatingUrgencyCollection($time_horizons);
    }

    public static function status_action()
    {
        $pagination = request('pagination.pageSize');
        $search = request('search');
        $dimension = request('dimension');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $status_actions = self::with(['dimension', 'rating.action_reasons' => function ($q) {
            $q->select('driving_force_rating_id' ,'date', 'reason', 'status_action_id')->orderBy('date', 'ASC');
        }])
            ->when($search, function ($q) use ($search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $date_start)
            ->has('rating')
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('impact_analysis')
                    ->whereNotNull('uncertainty_analysis');
            })
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'DESC')
            ->paginate($pagination);

        return new StatusActionCollection($status_actions);
    }
}
