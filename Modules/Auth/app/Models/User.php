<?php

namespace Modules\Auth\app\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Events\app\Models\Favorite;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Models\Ticket;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'status',
        'phone',
        'timezone',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'account_type',
        'organizer_type',
    ];

    protected $hidden = [
        'password',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    public function emailVerifications()
    {
        return $this->hasMany(EmailVerification::class);
    }

    public function passwordresettoken()
    {
        return $this->hasOne(PasswordResetToken::class);
    }

    public function ownedOrganizers()
    {
        return $this->hasOne(Organizer::class, 'user_id');
    }

    public function favourites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function organizers()
    {
        return $this->belongsToMany(Organizer::class, 'organizer_members', 'user_id', 'organizer_id')
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }
}
