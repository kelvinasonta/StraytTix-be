<?php

namespace Modules\Events\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Models\TicketType;

class EventsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure an organizer exists or create a default one
        $organizer = Organizer::first();
        if (! $organizer) {
            $user = User::first() ?? User::create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'organizer@eventsos.com',
                'password' => bcrypt('password'),
                'account_type' => 'organizer',
                'email_verified_at' => now(),
            ]);

            $organizer = Organizer::create([
                'user_id' => $user->id,
                'name' => 'Prime Events Ltd',
                'slug' => 'prime-events-ltd',
                'type' => OrganizerType::ORGANIZATION,
                'description' => 'Leading event organizers for global tech and creative summits.',
            ]);
        }

        $userId = $organizer->user_id;

        $events = [
            [
                'title' => 'Africa Tech Expo 2026',
                'description' => 'The premier tech showcase featuring AI startups, founders, and venture capitalists across the continent.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::PHYSICAL,
                'event_format' => 'Conference',
                'event_category' => ['Tech', 'Startups', 'AI'],
                'venue_name' => 'Landmark Event Centre',
                'venue_address' => 'Plot 2&3 Water Corporation Drive',
                'street_address' => 'Oniru, Victoria Island',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'zip' => '101241',
                'landmark' => 'Near Landmark Beach',
                'latitude' => 6.4253,
                'longitude' => 3.4474,
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Lagos',
                'start_date' => now()->addDays(7)->toDateString(),
                'start_time' => '09:00:00',
                'end_date' => now()->addDays(9)->toDateString(),
                'end_time' => '17:00:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&auto=format&fit=crop',
                'is_featured' => true,
                'schedule_items' => [
                    ['title' => 'Keynote: The Future of African AI', 'start_time' => '09:30:00', 'end_time' => '11:00:00', 'sort_order' => 1],
                    ['title' => 'Venture Capital & Startup Pitches', 'start_time' => '11:30:00', 'end_time' => '15:00:00', 'sort_order' => 2],
                ],
                'lineup' => [
                    ['name' => 'Chidi Okonkwo', 'role' => 'VP of Engineering', 'bio' => 'Building resilient fintech infrastructures.', 'sort_order' => 1],
                    ['name' => 'Amara Diallo', 'role' => 'Founding Partner', 'bio' => 'Early-stage tech investor in emerging markets.', 'sort_order' => 2],
                ],
                'tickets' => [
                    ['name' => 'Early Bird Access', 'type' => 'paid', 'price' => 15000, 'quantity' => 200, 'ticket_capacity' => 'limited'],
                    ['name' => 'VIP All-Access Pass', 'type' => 'paid', 'price' => 50000, 'quantity' => 50, 'ticket_capacity' => 'limited'],
                    ['name' => 'Student Pass', 'type' => 'free', 'price' => 0, 'quantity' => 100, 'ticket_capacity' => 'limited'],
                ],
            ],
            [
                'title' => 'Global Product Design Masterclass',
                'description' => 'Hands-on remote workshop covering design systems, Figma tokens, and micro-interactions.',
                'event_country' => 'United States',
                'event_type' => EventType::VIRTUAL,
                'event_format' => 'Workshop',
                'event_category' => ['Design', 'UI/UX', 'Product'],
                'online_meeting_url' => 'https://meet.google.com/abc-defg-hij',
                'schedule_type' => 'single',
                'schedule_timezone' => 'America/New_York',
                'start_date' => now()->addDays(12)->toDateString(),
                'start_time' => '14:00:00',
                'end_date' => now()->addDays(12)->toDateString(),
                'end_time' => '18:00:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=1200&auto=format&fit=crop',
                'is_featured' => false,
                'tickets' => [
                    ['name' => 'Workshop Ticket', 'type' => 'paid', 'price' => 25000, 'quantity' => 150, 'ticket_capacity' => 'limited'],
                ],
            ],
            [
                'title' => 'Lagos Music & Culture Festival',
                'description' => 'A weekend celebration of Afrobeat, street food, interactive art installations, and live stage performances.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::PHYSICAL,
                'event_format' => 'Festival',
                'event_category' => ['Music', 'Culture', 'Food', 'Art'],
                'venue_name' => 'Tafawa Balewa Square (TBS)',
                'venue_address' => '45/57 Catholic Mission St',
                'street_address' => 'Lagos Island',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'zip' => '100221',
                'latitude' => 6.4474,
                'longitude' => 3.4022,
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Lagos',
                'start_date' => now()->addDays(20)->toDateString(),
                'start_time' => '16:00:00',
                'end_date' => now()->addDays(22)->toDateString(),
                'end_time' => '23:30:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=1200&auto=format&fit=crop',
                'is_featured' => true,
                'tickets' => [
                    ['name' => 'Regular Entry', 'type' => 'paid', 'price' => 5000, 'quantity' => 1000, 'ticket_capacity' => 'limited'],
                    ['name' => 'VIP Table for 5', 'type' => 'paid', 'price' => 150000, 'quantity' => 20, 'ticket_capacity' => 'limited'],
                ],
            ],
            [
                'title' => 'Fintech Regulatory Compliance Workshop',
                'description' => 'Deep dive with legal and regulatory experts on open banking, PCI-DSS compliance, and cross-border remittances.',
                'event_country' => 'Kenya',
                'event_type' => EventType::VIRTUAL,
                'event_format' => 'Webinar',
                'event_category' => ['Fintech', 'Legal', 'Compliance'],
                'online_meeting_url' => 'https://teams.microsoft.com/l/meetup-join/fintech-compliance',
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Nairobi',
                'start_date' => now()->addDays(25)->toDateString(),
                'start_time' => '11:00:00',
                'end_date' => now()->addDays(25)->toDateString(),
                'end_time' => '14:00:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1450133064473-71024230f91b?w=1200&auto=format&fit=crop',
                'is_featured' => false,
                'tickets' => [
                    ['name' => 'Free Registration', 'type' => 'free', 'price' => 0, 'quantity' => 500, 'ticket_capacity' => 'limited'],
                ],
            ],
            [
                'title' => 'AI Hackathon: Build with LLMs',
                'description' => '48-hour continuous hackathon to prototype autonomous AI agents and multimodal tools. $10,000 prize pool.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::PHYSICAL,
                'event_format' => 'Hackathon',
                'event_category' => ['AI', 'Hackathon', 'Coding'],
                'venue_name' => 'Zone Tech Park',
                'street_address' => 'Gbagada Expressway',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Lagos',
                'start_date' => now()->addDays(30)->toDateString(),
                'start_time' => '18:00:00',
                'end_date' => now()->addDays(32)->toDateString(),
                'end_time' => '19:00:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=1200&auto=format&fit=crop',
                'is_featured' => true,
                'tickets' => [
                    ['name' => 'Hacker Pass', 'type' => 'free', 'price' => 0, 'quantity' => 120, 'ticket_capacity' => 'limited'],
                ],
            ],
            [
                'title' => 'Modern Laravel & Vue 3 Masterclass',
                'description' => 'Advanced patterns in modular Laravel applications, background queues, and real-time frontend states.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::VIRTUAL,
                'event_format' => 'Masterclass',
                'event_category' => ['PHP', 'Laravel', 'Vue'],
                'online_meeting_url' => 'https://youtube.com/live/laravel-masterclass',
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Lagos',
                'start_date' => now()->addDays(14)->toDateString(),
                'start_time' => '15:00:00',
                'end_date' => now()->addDays(14)->toDateString(),
                'end_time' => '18:00:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=1200&auto=format&fit=crop',
                'is_featured' => true,
                'tickets' => [
                    ['name' => 'Live Access', 'type' => 'free', 'price' => 0, 'quantity' => 1000, 'ticket_capacity' => 'limited'],
                ],
            ],
        ];

        $locationFields = [
            'venue_name',
            'venue_address',
            'street_address',
            'city',
            'state',
            'zip',
            'landmark',
            'latitude',
            'longitude',
            'online_meeting_url',
        ];

        $scheduleFields = [
            'schedule_type',
            'schedule_timezone',
            'start_date',
            'start_time',
            'end_date',
            'end_time',
            'recurring_type',
            'event_frequency',
            'recurring_end_date',
            'recurring_end_time',
            'recurring_occurrences',
        ];

        foreach ($events as $eventData) {
            $locationData = [];
            foreach ($locationFields as $field) {
                if (isset($eventData[$field])) {
                    $locationData[$field] = $eventData[$field];
                    unset($eventData[$field]);
                }
            }

            $scheduleData = [];
            foreach ($scheduleFields as $field) {
                if (isset($eventData[$field])) {
                    $scheduleData[$field] = $eventData[$field];
                    unset($eventData[$field]);
                }
            }

            $lineupData = $eventData['lineup'] ?? [];
            unset($eventData['lineup']);

            $scheduleItemsData = $eventData['schedule_items'] ?? [];
            unset($eventData['schedule_items']);

            $ticketsData = $eventData['tickets'] ?? [];
            unset($eventData['tickets']);

            $event = Event::updateOrCreate(
                [
                    'organizer_id' => $organizer->id,
                    'slug' => Str::slug($eventData['title']),
                ],
                array_merge($eventData, [
                    'user_id' => $userId,
                ])
            );

            if (! empty($locationData)) {
                $location = $event->location()->updateOrCreate(
                    ['event_id' => $event->id],
                    $locationData
                );
                $event->update(['event_location_id' => $location->id]);
            }

            if (! empty($scheduleData)) {
                $schedule = $event->schedule()->updateOrCreate(
                    ['event_id' => $event->id],
                    $scheduleData
                );
                $event->update(['event_schedule_id' => $schedule->id]);
            }

            if (! empty($scheduleItemsData)) {
                foreach ($scheduleItemsData as $item) {
                    $event->scheduleItems()->updateOrCreate(
                        ['event_id' => $event->id, 'title' => $item['title']],
                        $item
                    );
                }
            }

            if (! empty($lineupData)) {
                foreach ($lineupData as $speaker) {
                    $event->lineup()->updateOrCreate(
                        ['event_id' => $event->id, 'name' => $speaker['name']],
                        $speaker
                    );
                }
            }

            if (! empty($ticketsData)) {
                foreach ($ticketsData as $ticket) {
                    TicketType::updateOrCreate(
                        ['event_id' => $event->id, 'name' => $ticket['name']],
                        array_merge($ticket, ['event_id' => $event->id])
                    );
                }
            }
        }
    }
}
