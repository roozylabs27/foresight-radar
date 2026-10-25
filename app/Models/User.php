<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Http\Resources\UserCollection;
use App\Support\TableSort;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property-read Collection<int, UserLlmSetting> $llmSettings
 *
 * @method \Illuminate\Database\Eloquent\Relations\HasMany llmSettings()
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'email',
        'password',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->translatedFormat('D, d F Y')
        );
    }

    public static function filter()
    {
        $rawPagination = (int) request('pagination.pageSize', request('pageSize', 10));
        $pagination = min(100, max(1, $rawPagination > 0 ? $rawPagination : 10));
        $search = request('search');
        $date_range = request('date');
        $has_date = is_array($date_range) && count($date_range) === 2 && ! empty($date_range[0]) && ! empty($date_range[1]);

        if (in_array('developer', auth()->user()->roles->pluck('name')->toArray())) {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name')->orderBy('id');
            });
        } elseif (in_array('super-admin', auth()->user()->roles->pluck('name')->toArray())) {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name');
            })->whereHas('roles', function ($q) {
                $q->whereNotIn('name', ['qa', 'developer', 'token']);
            });
        } else {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name');
            })->whereHas('roles', function ($q) {
                $q->whereNotIn('name', ['super-admin', 'qa', 'developer']);
            });
        }

        $users = $users->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'LIKE', $search.'%')
                    ->orWhere('email', 'LIKE', $search.'%');
            });
        })
            ->when($has_date, function ($q) use ($date_range) {
                $q->where('created_at', '>=', $date_range[0].' 00:00:00')
                    ->where('created_at', '<=', $date_range[1].' 23:59:59');
            })
            ->tap(fn ($q) => TableSort::apply(
                $q,
                request()->all(),
                [
                    'date_created' => 'created_at',
                    'name' => 'name',
                    'email' => 'email',
                    // A user's first role by display name, as listed in the Role/Division column.
                    'role' => Role::select('display_name')
                        ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->whereColumn('model_has_roles.model_id', 'users.id')
                        ->where('model_has_roles.model_type', self::class)
                        ->orderBy('display_name')
                        ->limit(1),
                ],
                fn ($q) => $q->orderBy('created_at', 'desc')
            ))
            ->paginate($pagination);

        return new UserCollection($users);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function llmSettings(): HasMany
    {
        return $this->hasMany(UserLlmSetting::class);
    }
}
