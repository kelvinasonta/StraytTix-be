<?php

declare(strict_types=1);

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventSetting extends Model
{
    use HasFactory;

    protected $table = 'event_settings';

    protected $attributes = [
        'confirmation_email_msg' => 'Thank you for your purchase! We appreciate your support and look forward to seeing you at the event.',
        'pre_sale_message' => 'Tickets will go on sale soon.',
        'post_sale_message' => 'Ticket sales have ended. Thank you for your interest and support!',
        'is_status_msg_enabled' => false,
    ];

    protected $fillable = [
        'uuid',
        'event_id',
        'stream_provider',
        'stream_instructions',
        'refund_policy',
        'refund_option',
        'age_restriction',
        'dress_code',
        'event_rules',
        'amenities',
        'confirmation_email_msg',
        'status_message',
        'is_status_msg_enabled',
        'status_message_type',
        'pre_sale_message',
        'post_sale_message',
        'theme_color',
        'banner_style',
        'faqs',
        'gallery_urls',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'is_status_msg_enabled' => 'boolean',
            'faqs' => 'array',
            'gallery_urls' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventSetting $setting) {
            if (empty($setting->uuid)) {
                $setting->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
