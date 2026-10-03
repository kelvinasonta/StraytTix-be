<?php

namespace Modules\Auth\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Modules\Auth\database\Factories\LoginAttemptsFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempts extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */

    // public $timestamps = false;
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'metadata',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // protected static function newFactory(): LoginAttemptsFactory
    // {
    //     // return LoginAttemptsFactory::new();
    // }
}
