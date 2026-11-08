<?php

declare(strict_types=1);

namespace Modules\Events\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Actions\EventCreateAction;
use Modules\Events\app\Actions\EventCreateLineupAction;
use Modules\Events\app\Actions\EventCreateScheduleAction;
use Modules\Events\app\Actions\EventCreateScheduleItemAction;
use Modules\Events\app\Actions\EventCustomQuestionCreateAction;
use Modules\Events\app\Actions\EventDuplicateAction;
use Modules\Events\app\Actions\EventLocationCreateAction;
use Modules\Events\app\Actions\EventPromoterCreateAction;
use Modules\Events\app\Actions\EventSettingCreateAction;
use Modules\Events\app\DTOs\EventCustomQuestionDTO;
use Modules\Events\app\DTOs\EventDTO;
use Modules\Events\app\DTOs\EventLineupDTO;
use Modules\Events\app\DTOs\EventLocationDTO;
use Modules\Events\app\DTOs\EventPromoterDTO;
use Modules\Events\app\DTOs\EventScheduleDTO;
use Modules\Events\app\DTOs\EventScheduleItemDTO;
use Modules\Events\app\DTOs\EventSettingDTO;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Exceptions\CannotPublishWithoutTickets;
use Modules\Events\app\Exceptions\EventAlreadyExist;
use Modules\Events\app\Exceptions\PublishedAlready;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;

class EventService
{
    private const DEFAULT_RELATIONS = ['location', 'schedule', 'scheduleItems', 'lineup', 'ticketTypes', 'settings', 'customQuestions', 'promoters'];

    private const PUBLIC_RELATIONS = ['organizer', 'location', 'schedule', 'scheduleItems', 'lineup', 'ticketTypes', 'settings', 'customQuestions', 'promoters'];

    private const EVENT_FIELD_MAPPINGS = [
        'title' => 'title',
        'description' => 'description',
        'eventCountry' => 'event_country',
        'eventType' => 'event_type',
        'eventFormat' => 'event_format',
        'eventCategory' => 'event_category',
        'status' => 'status',
        'visibility' => 'visibility',
        'bannerUrl' => 'banner_url',
        'isFeatured' => 'is_featured',
        'meta' => 'meta',
    ];

    private const LOCATION_FIELD_MAPPINGS = [
        'venueName' => 'venue_name',
        'venueAddress' => 'venue_address',
        'streetAddress' => 'street_address',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'landmark' => 'landmark',
        'longitude' => 'longitude',
        'latitude' => 'latitude',
        'onlineMeetingUrl' => 'online_meeting_url',
    ];

    private const SCHEDULE_FIELD_MAPPINGS = [
        'scheduleType' => 'schedule_type',
        'scheduleTimezone' => 'schedule_timezone',
        'eventFrequency' => 'event_frequency',
        'startDate' => 'start_date',
        'startTime' => 'start_time',
        'endDate' => 'end_date',
        'endTime' => 'end_time',
        'recurringEndType' => 'recurring_type',
        'recurringEndDate' => 'recurring_end_date',
        'recurringOccurrences' => 'recurring_occurrences',
    ];

    private const SETTING_FIELD_MAPPINGS = [
        'streamProvider' => 'stream_provider',
        'streamInstructions' => 'stream_instructions',
        'refundPolicy' => 'refund_policy',
        'refundOption' => 'refund_option',
        'ageRestriction' => 'age_restriction',
        'dressCode' => 'dress_code',
        'eventRules' => 'event_rules',
        'amenities' => 'amenities',
        'confirmationEmailMsg' => 'confirmation_email_msg',
        'statusMessage' => 'status_message',
        'isStatusMsgEnabled' => 'is_status_msg_enabled',
        'statusMessageType' => 'status_message_type',
        'preSaleMessage' => 'pre_sale_message',
        'postSaleMessage' => 'post_sale_message',
        'themeColor' => 'theme_color',
        'bannerStyle' => 'banner_style',
        'faqs' => 'faqs',
        'galleryUrls' => 'gallery_urls',
    ];

    public function __construct(
        protected EventCreateAction $eventCreateAction,
        protected EventCreateScheduleAction $eventCreateScheduleAction,
        protected EventCreateScheduleItemAction $eventCreateScheduleItemAction,
        protected EventCreateLineupAction $eventCreateLineupAction,
        protected EventLocationCreateAction $eventLocationCreateAction,
        protected EventDuplicateAction $eventDuplicateAction,
        protected EventSettingCreateAction $eventSettingCreateAction,
        protected EventCustomQuestionCreateAction $eventCustomQuestionCreateAction,
        protected EventPromoterCreateAction $eventPromoterCreateAction,
        protected AuditService $auditService,
    ) {}

    public function getOrganizerEvents(Organizer $organizer, array $filters = []): LengthAwarePaginator
    {
        $query = Event::with(['location', 'schedule', 'settings', 'ticketTypes'])
            ->where('organizer_id', $organizer->id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->whereHas('assignedStaff', function (Builder $q) use ($filters) {
                $q->where('users.id', $filters['assigned_user_id']);
            });
        }

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($filters['perPage'] ?? 15);
    }

    public function getOrganizerEvent(Organizer $organizer, string $eventUuid): Event
    {
        return Event::with(self::DEFAULT_RELATIONS)
            ->where('organizer_id', $organizer->id)
            ->where('uuid', $eventUuid)
            ->firstOrFail();
    }

    /**
     * Create a new event for an organizer.
     *
     * @param  array<int, EventScheduleItemDTO>  $scheduleItemDto
     * @param  array<int, EventLineupDTO>  $lineupDto
     * @param  array<int, EventCustomQuestionDTO>  $customQuestionDtos
     * @param  array<int, EventPromoterDTO>  $promoterDtos
     */
    public function createEvent(
        Organizer $organizer,
        User $creator,
        EventDTO $eventDto,
        EventScheduleDTO $scheduleDto,
        EventLocationDTO $eventLocationDto,
        array $scheduleItemDto = [],
        array $lineupDto = [],
        ?EventSettingDTO $settingDto = null,
        array $customQuestionDtos = [],
        array $promoterDtos = [],
    ): Event {
        return DB::transaction(function () use (
            $organizer,
            $creator,
            $eventDto,
            $scheduleDto,
            $eventLocationDto,
            $scheduleItemDto,
            $lineupDto,
            $settingDto,
            $customQuestionDtos,
            $promoterDtos,
        ) {
            $eventExists = Event::where('title', $eventDto->title)
                ->where('organizer_id', $organizer->id)
                ->where('status', '!=', EventStatus::DELETED)
                ->exists();

            if ($eventExists) {
                throw new EventAlreadyExist($eventDto->title);
            }

            $event = $this->eventCreateAction->execute($organizer, $creator, $eventDto);

            $this->eventCreateScheduleAction->execute($event, $scheduleDto);

            if (! empty($scheduleItemDto)) {
                $this->eventCreateScheduleItemAction->execute($event, $scheduleItemDto);
            }

            if (! empty($lineupDto)) {
                $this->eventCreateLineupAction->execute($event, $lineupDto);
            }

            if ($eventLocationDto->hasLocation()) {
                $this->eventLocationCreateAction->execute($event, $eventLocationDto);
            }

            if ($settingDto !== null) {
                $this->eventSettingCreateAction->execute($event, $settingDto);
            }

            if (! empty($customQuestionDtos)) {
                $this->eventCustomQuestionCreateAction->execute($event, $customQuestionDtos);
            }

            if (! empty($promoterDtos)) {
                $this->eventPromoterCreateAction->execute($event, $promoterDtos);
            }

            $this->auditService->log(
                action: 'event.created',
                description: "Event '{$event->title}' was created",
                organizer: $organizer,
                user: $creator,
                metadata: [
                    'event_id' => $event->id,
                    'event_uuid' => $event->uuid,
                ]
            );

            return $event->loadMissing(self::DEFAULT_RELATIONS);
        });
    }

    public function updateEvent(Organizer $organizer, User $creator, string $eventUuid, array $data): Event
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        return DB::transaction(function () use ($event, $organizer, $creator, $data) {
            $changes = [];

            $payload = $this->mapDataToColumns($data, self::EVENT_FIELD_MAPPINGS);
            if (array_key_exists('faqs', $data) || array_key_exists('galleryUrls', $data)) {
                $meta = $event->meta ?? [];
                if (array_key_exists('faqs', $data)) {
                    $meta['faqs'] = $data['faqs'];
                }
                if (array_key_exists('galleryUrls', $data)) {
                    $meta['galleryUrls'] = $data['galleryUrls'];
                }
                $payload['meta'] = $meta;
            }

            if (! empty($payload)) {
                $event->fill($payload);
                foreach ($event->getDirty() as $key => $newValue) {
                    $changes[$key] = [
                        'old' => $event->getOriginal($key),
                        'new' => $newValue,
                    ];
                }
                $event->save();
            }

            $locationPayload = $this->mapDataToColumns($data, self::LOCATION_FIELD_MAPPINGS);
            if (! empty($locationPayload)) {
                $location = $event->location()->firstOrNew();
                $location->fill($locationPayload);
                foreach ($location->getDirty() as $key => $newValue) {
                    $changes['location'][$key] = [
                        'old' => $location->getOriginal($key),
                        'new' => $newValue,
                    ];
                }
                $location->save();
            }

            $schedulePayload = $this->mapDataToColumns($data, self::SCHEDULE_FIELD_MAPPINGS);
            if (! empty($schedulePayload)) {
                $schedule = $event->schedule()->firstOrNew();
                $schedule->fill($schedulePayload);
                foreach ($schedule->getDirty() as $key => $newValue) {
                    $changes['schedule'][$key] = [
                        'old' => $schedule->getOriginal($key),
                        'new' => $newValue,
                    ];
                }
                $schedule->save();
            }

            // 4. Update agenda / schedule items (if provided)
            if (array_key_exists('schedules', $data) && is_array($data['schedules'])) {
                $scheduleItemDtos = array_map(
                    fn (array $item) => EventScheduleItemDTO::fromArray($item),
                    $data['schedules']
                );

                $event->scheduleItems()->delete();

                if (! empty($scheduleItemDtos)) {
                    $this->eventCreateScheduleItemAction->execute($event, $scheduleItemDtos);
                }

                $changes['schedules'] = [
                    'count' => count($scheduleItemDtos),
                ];
            }

            // 5. Update lineup (if provided)
            if (array_key_exists('lineup', $data) && is_array($data['lineup'])) {
                $lineupDtos = array_map(
                    fn (array $item) => EventLineupDTO::fromArray($item),
                    $data['lineup']
                );

                $event->lineup()->delete();

                if (! empty($lineupDtos)) {
                    $this->eventCreateLineupAction->execute($event, $lineupDtos);
                }

                $changes['lineup'] = [
                    'count' => count($lineupDtos),
                ];
            }

            // 6. Update settings (if provided)
            $settingsPayload = $this->mapDataToColumns($data, self::SETTING_FIELD_MAPPINGS);
            if (isset($data['settings']) && is_array($data['settings'])) {
                $subSettings = $this->mapDataToColumns($data['settings'], self::SETTING_FIELD_MAPPINGS);
                $settingsPayload = array_merge($settingsPayload, $subSettings);
            }
            if (isset($data['gallery']) && ! isset($settingsPayload['gallery_urls'])) {
                $settingsPayload['gallery_urls'] = $data['gallery'];
            }
            if (isset($data['faqs']) && ! isset($settingsPayload['faqs'])) {
                $settingsPayload['faqs'] = $data['faqs'];
            }
            if (isset($data['galleryUrls']) && ! isset($settingsPayload['gallery_urls'])) {
                $settingsPayload['gallery_urls'] = $data['galleryUrls'];
            }

            if (! empty($settingsPayload)) {
                $setting = $event->settings()->firstOrNew();
                $setting->fill($settingsPayload);
                foreach ($setting->getDirty() as $key => $newValue) {
                    $changes['settings'][$key] = [
                        'old' => $setting->getOriginal($key),
                        'new' => $newValue,
                    ];
                }
                $setting->save();
            }

            // 7. Update custom questions (if provided)
            if (array_key_exists('customQuestions', $data) && is_array($data['customQuestions'])) {
                $questionDtos = array_map(
                    fn (array $item) => EventCustomQuestionDTO::fromArray($item),
                    $data['customQuestions']
                );

                $event->customQuestions()->delete();

                if (! empty($questionDtos)) {
                    $this->eventCustomQuestionCreateAction->execute($event, $questionDtos);
                }

                $changes['customQuestions'] = [
                    'count' => count($questionDtos),
                ];
            }

            // 8. Update promoters (if provided)
            if (array_key_exists('promoters', $data) && is_array($data['promoters'])) {
                $promoterDtos = array_map(
                    fn (array $item) => EventPromoterDTO::fromArray($item),
                    $data['promoters']
                );

                $event->promoters()->delete();

                if (! empty($promoterDtos)) {
                    $this->eventPromoterCreateAction->execute($event, $promoterDtos);
                }

                $changes['promoters'] = [
                    'count' => count($promoterDtos),
                ];
            }

            $this->auditService->log(
                action: 'event.updated',
                description: "Event '{$event->title}' was updated",
                organizer: $organizer,
                user: $creator,
                metadata: [
                    'event_id' => $event->id,
                    'event_uuid' => $event->uuid,
                    'changes' => $changes,
                    'event' => $event,
                ]
            );

            return $event->fresh(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(Organizer $organizer, User $creator, string $eventUuid): bool
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        $event->update([
            'status' => EventStatus::DELETED,
            'visibility' => EventVisibility::DELETED,
        ]);
        $event->delete();

        $this->auditService->log(
            action: 'event.deleted',
            description: "Event '{$event->title}' was deleted",
            organizer: $organizer,
            user: $creator,
            metadata: []
        );

        return true;
    }

    public function publishEvent(Organizer $organizer, User $creator, string $eventUuid): Event
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        if ($event->status === EventStatus::PUBLISHED) {
            throw new PublishedAlready;
        }

        if ($event->ticketTypes()->count() === 0) {
            throw new CannotPublishWithoutTickets;
        }

        return $this->updateEventStatus(
            organizer: $organizer,
            creator: $creator,
            event: $event,
            status: EventStatus::PUBLISHED,
            action: 'event.published',
            description: "Event '{$event->title}' was published"
        );
    }

    public function unpublishEvent(Organizer $organizer, User $creator, string $eventUuid): Event
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        return $this->updateEventStatus(
            organizer: $organizer,
            creator: $creator,
            event: $event,
            status: EventStatus::DRAFT,
            action: 'event.unpublished',
            description: "Event '{$event->title}' was unpublished back to draft"
        );
    }

    public function duplicateEvent(Organizer $organizer, User $creator, string $eventUuid): Event
    {
        $sourceEvent = $this->getOrganizerEvent($organizer, $eventUuid);

        return DB::transaction(function () use ($sourceEvent, $organizer, $creator) {
            $newEvent = $this->eventDuplicateAction->execute($sourceEvent, $organizer, $creator);

            $this->auditService->log(
                action: 'event.duplicated',
                description: "Event '{$sourceEvent->title}' was duplicated to '{$newEvent->title}'",
                organizer: $organizer,
                user: $creator,
                metadata: [
                    'source_event_id' => $sourceEvent->id,
                    'source_event_uuid' => $sourceEvent->uuid,
                    'new_event_id' => $newEvent->id,
                    'new_event_uuid' => $newEvent->uuid,
                ]
            );

            return $newEvent;
        });
    }

    public function getEventStats(Organizer $organizer, string $eventUuid): array
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        $ticketTypes = $event->ticketTypes;
        $hasUnlimited = $ticketTypes->contains(fn ($tt) => $tt->ticket_capacity === 'unlimited');

        $totalCapacity = $hasUnlimited ? null : (int) $ticketTypes->sum('quantity');
        $soldTickets = (int) $ticketTypes->sum('sold_quantity');
        $reservedTickets = (int) $ticketTypes->sum('reserved_quantity');
        $remainingTickets = $hasUnlimited ? null : max(0, (int) $totalCapacity - $soldTickets - $reservedTickets);

        $totalRevenue = 0.0;
        foreach ($ticketTypes as $tt) {
            $totalRevenue += ((float) $tt->price) * ((int) $tt->sold_quantity);
        }

        $checkedInCount = $event->tickets()->whereNotNull('checked_in_at')->count();
        $favoritesCount = $event->favorites()->count();
        $currency = $ticketTypes->first()?->currency ?? 'NGN';

        return [
            'totalCapacity' => $totalCapacity,
            'isCapacityUnlimited' => $hasUnlimited,
            'soldTickets' => $soldTickets,
            'reservedTickets' => $reservedTickets,
            'remainingTickets' => $remainingTickets,
            'totalRevenue' => round($totalRevenue, 2),
            'currency' => $currency,
            'checkedInCount' => $checkedInCount,
            'favoritesCount' => $favoritesCount,
            'ticketBreakdown' => $ticketTypes->map(fn ($tt) => [
                'id' => $tt->uuid,
                'name' => $tt->name,
                'type' => $tt->type,
                'price' => (float) $tt->price,
                'currency' => $tt->currency,
                'quantity' => $tt->quantity,
                'soldQuantity' => (int) $tt->sold_quantity,
                'reservedQuantity' => (int) $tt->reserved_quantity,
                'revenue' => round(((float) $tt->price) * ((int) $tt->sold_quantity), 2),
                'status' => $tt->status,
            ])->values()->all(),
        ];
    }

    public function cancelEvent(Organizer $organizer, User $creator, string $eventUuid): Event
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        return $this->updateEventStatus(
            organizer: $organizer,
            creator: $creator,
            event: $event,
            status: EventStatus::CANCELLED,
            action: 'event.canceled',
            description: "Event '{$event->title}' was canceled"
        );
    }

    public function archiveEvent(Organizer $organizer, User $creator, string $eventUuid): Event
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        return $this->updateEventStatus(
            organizer: $organizer,
            creator: $creator,
            event: $event,
            status: EventStatus::ARCHIVED,
            action: 'event.archived',
            description: "Event '{$event->title}' was archived"
        );
    }

    public function getPublicEvents(array $filters = []): LengthAwarePaginator
    {
        $query = Event::with(self::PUBLIC_RELATIONS)
            ->published()
            ->public()
            ->upcoming();

        $this->applyFilters($query, $filters);

        return $query->paginate($filters['perPage'] ?? 12);
    }

    /**
     * Public show event by slug or UUID.
     */
    public function getPublicEventBySlug(string $slug): Event
    {
        return Event::with(self::PUBLIC_RELATIONS)
            ->published()
            ->public()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Map request input keys to database column names.
     */
    private function mapDataToColumns(array $data, array $mappings): array
    {
        $payload = [];
        foreach ($mappings as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $payload[$column] = $data[$requestKey];
            }
        }

        return $payload;
    }

    /**
     * Apply common search, category, and eventType filters to an event query.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('location', function (Builder $lq) use ($search) {
                        $lq->where('venue_name', 'like', $search);
                    });
            });
        }

        if (! empty($filters['eventType'])) {
            $query->where('event_type', $filters['eventType']);
        }

        if (! empty($filters['category']) && strtolower((string) $filters['category']) !== 'all') {
            $cat = '%'.$filters['category'].'%';
            $query->where(function (Builder $q) use ($cat) {
                $q->where('event_format', 'like', $cat)
                    ->orWhere('event_category', 'like', $cat);
            });
        }

        if (! empty($filters['organizerUuid'])) {
            $orgUuid = (string) $filters['organizerUuid'];
            $query->whereHas('organizer', function (Builder $oq) use ($orgUuid) {
                $oq->where('uuid', $orgUuid)->orWhere('slug', $orgUuid);
            });
        }
    }

    /**
     * Update event status, log to audit trail, and return freshly loaded event.
     */
    private function updateEventStatus(
        Organizer $organizer,
        User $creator,
        Event $event,
        EventStatus $status,
        string $action,
        string $description,
    ): Event {
        $event->update(['status' => $status]);

        $this->auditService->log(
            action: $action,
            description: $description,
            organizer: $organizer,
            user: $creator,
            metadata: []
        );

        return $event->fresh(self::DEFAULT_RELATIONS);
    }
}
