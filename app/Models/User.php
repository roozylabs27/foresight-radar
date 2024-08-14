<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Http\Resources\UserCollection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
    ];

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
        $pagination = request('pagination.pageSize');
        $search = request('search');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        if (in_array('developer', auth()->user()->roles->pluck('name')->toArray())) {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name')->orderBy('id');
            });
        } else if (in_array('super-admin', auth()->user()->roles->pluck('name')->toArray())) {
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
            $q->where('name', 'LIKE',  $search . '%')
                ->orWhere('email', 'LIKE', $search . '%');
        })
            ->where('created_at', '>=', $date_start)
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'desc')
            ->paginate($pagination);


        return new UserCollection($users);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
