<?php

namespace Modules\Organizer\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Organizer\Database\Factories\OrganizerBankAccountsFactory;

class OrganizerBankAccounts extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'organizer_id',
        'account_number',
        'bank_name',
        'account_name',
        'bank_code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'account_number' => 'encrypted',
        'bank_name' => 'encrypted',
        'account_name' => 'encrypted',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
