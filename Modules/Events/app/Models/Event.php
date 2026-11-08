<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Models\Ticket;
use Modules\Tickets\app\Models\TicketType;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'organizer_id',
        'user_id',
        'title',
        'slug',
        'description',
        'event_country',
        'event_type',
        'event_format',
        'event_category',
        'event_location_id',
        'event_schedule_id',
        'event_lineup_id',
        'status',
        'visibility',
        'banner_url',
        'is_featured',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'status' => EventStatus::class,
            'visibility' => EventVisibility::class,
            'event_category' => 'array',
            'is_featured' => 'boolean',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->slug) && ! empty($event->title)) {
                $event->slug = Str::slug($event->title);
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function location(): HasOne
    {
        return $this->hasOne(EventLocation::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(EventSchedule::class);
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(EventScheduleItem::class)->orderBy('schedule_date')->orderBy('start_time');
    }

    public function lineup(): HasMany
    {
        return $this->hasMany(EventLineup::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(EventCollaborator::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(EventMetric::class, 'event_id');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(EventStaff::class, 'event_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(EventSetting::class);
    }

    public function customQuestions(): HasMany
    {
        return $this->hasMany(EventCustomQuestion::class)->orderBy('sort_order');
    }

    public function promoters(): HasMany
    {
        return $this->hasMany(EventPromoter::class)->orderBy('sort_order');
    }

    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_staff', 'event_id', 'user_id')
            ->withPivot(['id', 'organizer_member_id', 'assigned_by', 'role', 'permissions', 'notes'])
            ->withTimestamps();
    }

    /**
     * Scope for published events.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::PUBLISHED);
    }

    /**
     * Scope for public visibility.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', EventVisibility::PUBLIC);
    }

    /**
     * Scope for upcoming events.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereHas('schedule', function ($sq) {
                $sq->where(function ($sub) {
                    $sub->whereNull('end_date')
                        ->where('start_date', '>=', now()->toDateString())
                        ->orWhere('end_date', '>=', now()->toDateString());
                });
            });
        });
    }
}
