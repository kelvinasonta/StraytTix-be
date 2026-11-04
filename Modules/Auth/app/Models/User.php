<?php

namespace Modules\Auth\app\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'password_changed_at',
        'phone_verified_at',
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
            'password_changed_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_admin' => 'boolean',
            'admin_permissions' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Determine if the user is an active administrator.
     */
    public function isAdministrator(): bool
    {
        return (bool) $this->is_admin && $this->status === UserStatus::ACTIVE && $this->suspended_at === null;
    }

    /**
     * Check if administrator has a specific permission.
     */
    public function hasAdminPermission(string $permission): bool
    {
        if (! $this->isAdministrator()) {
            return false;
        }

        $permissions = $this->admin_permissions ?? [];

        // Wildcard super-admin
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
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

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    public function passwordResetToken(): HasOne
    {
        return $this->hasOne(PasswordResetToken::class);
    }

    public function ownedOrganizers(): HasOne
    {
        return $this->hasOne(Organizer::class, 'user_id');
    }

    public function favourites(): HasMany
    {
        return $this->favorites();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class, 'organizer_members', 'user_id', 'organizer_id')
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }
}
