<?php

namespace Modules\Events\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;

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
                'name' => 'John Doe',
                'email' => 'organizer@eventsos.com',
                'password' => bcrypt('password'),
                'account_type' => 'organizer',
                'email_verified_at' => now(),
            ]);

            $organizer = Organizer::create([
                'user_id' => $user->id,
                'name' => 'Prime Events Ltd',
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
                'start_time' => '09:00',
                'end_date' => now()->addDays(9)->toDateString(),
                'end_time' => '17:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&auto=format&fit=crop',
                'is_featured' => true,
                'schedules' => [
                    ['day' => 'Day 1', 'title' => 'Keynote: The Future of African AI', 'startTime' => '09:30', 'endTime' => '11:00'],
                    ['day' => 'Day 2', 'title' => 'Venture Capital & Startup Pitches', 'startTime' => '11:30', 'endTime' => '15:00'],
                ],
                'lineup' => [
                    ['name' => 'Chidi Okonkwo', 'role' => 'VP of Engineering', 'bio' => 'Building resilient fintech infrastructures.'],
                    ['name' => 'Amara Diallo', 'role' => 'Founding Partner', 'bio' => 'Early-stage tech investor in emerging markets.'],
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
                'start_time' => '14:00',
                'end_date' => now()->addDays(12)->toDateString(),
                'end_time' => '18:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=1200&auto=format&fit=crop',
                'is_featured' => false,
            ],
            [
                'title' => 'Lagos Music & Culture Festival',
                'description' => 'A weekend celebration of Afrobeat, street food, interactive art installations, and live stage performances.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::PHYSICAL,
                'event_format' => 'Festival',
                'event_category' => ['Music', 'Culture', 'Entertainment'],
                'venue_name' => 'Tafawa Balewa Square',
                'venue_address' => 'TBS Complex, 45/57 Catholic Mission St',
                'city' => 'Lagos Island',
                'state' => 'Lagos',
                'zip' => '100221',
                'latitude' => 6.4474,
                'longitude' => 3.4022,
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Lagos',
                'start_date' => now()->addDays(20)->toDateString(),
                'start_time' => '16:00',
                'end_date' => now()->addDays(22)->toDateString(),
                'end_time' => '23:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=1200&auto=format&fit=crop',
                'is_featured' => true,
            ],
            [
                'title' => 'DevOps & Cloud Native Summit',
                'description' => 'Learn how world-class engineering teams run Kubernetes at scale, automate CI/CD, and achieve zero downtime.',
                'event_country' => 'United Kingdom',
                'event_type' => EventType::HYBRID,
                'event_format' => 'Summit',
                'event_category' => ['DevOps', 'Cloud', 'Kubernetes'],
                'venue_name' => 'Excel London',
                'venue_address' => 'Royal Victoria Dock, 1 Western Gateway',
                'city' => 'London',
                'state' => 'Greater London',
                'zip' => 'E16 1XL',
                'online_meeting_url' => 'https://zoom.us/j/1234567890',
                'schedule_type' => 'single',
                'schedule_timezone' => 'Europe/London',
                'start_date' => now()->addDays(15)->toDateString(),
                'start_time' => '10:00',
                'end_date' => now()->addDays(16)->toDateString(),
                'end_time' => '17:30',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1200&auto=format&fit=crop',
                'is_featured' => true,
            ],
            [
                'title' => 'Weekly Founders Breakfast Meetup',
                'description' => 'Casual weekly recurring networking and coffee for early-stage startup founders to share growth lessons.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::PHYSICAL,
                'event_format' => 'Networking',
                'event_category' => ['Startups', 'Networking'],
                'venue_name' => 'Café Neo Victoria Island',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'schedule_type' => 'recurring',
                'schedule_timezone' => 'Africa/Lagos',
                'event_frequency' => 'weekly',
                'start_date' => now()->addDays(3)->toDateString(),
                'start_time' => '08:30',
                'end_date' => now()->addDays(3)->toDateString(),
                'end_time' => '10:30',
                'recurring_end_type' => 'after_occurrences',
                'recurring_occurrences' => 8,
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1515187029135-18ee286d815b?w=1200&auto=format&fit=crop',
                'is_featured' => false,
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
                'start_time' => '11:00',
                'end_date' => now()->addDays(25)->toDateString(),
                'end_time' => '14:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1450133064473-71024230f91b?w=1200&auto=format&fit=crop',
                'is_featured' => false,
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
                'start_time' => '18:00',
                'end_date' => now()->addDays(32)->toDateString(),
                'end_time' => '19:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=1200&auto=format&fit=crop',
                'is_featured' => true,
            ],
            [
                'title' => 'Enterprise Cybersecurity Roundtable',
                'description' => 'Closed-door VIP roundtable discussion for CISOs and heads of security on zero-trust architectures.',
                'event_country' => 'Ghana',
                'event_type' => EventType::PHYSICAL,
                'event_format' => 'Roundtable',
                'event_category' => ['Security', 'Enterprise'],
                'venue_name' => 'Kempinski Hotel Gold Coast City',
                'city' => 'Accra',
                'state' => 'Greater Accra',
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Accra',
                'start_date' => now()->addDays(18)->toDateString(),
                'start_time' => '13:00',
                'end_date' => now()->addDays(18)->toDateString(),
                'end_time' => '17:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PRIVATE,
                'banner_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1200&auto=format&fit=crop',
                'is_featured' => false,
            ],
            [
                'title' => 'Q4 Annual Strategic General Meeting',
                'description' => 'Internal board and shareholder meeting reviewing Q3 performance and 2027 roadmaps.',
                'event_country' => 'Nigeria',
                'event_type' => EventType::HYBRID,
                'event_format' => 'Meeting',
                'event_category' => ['Corporate', 'Strategy'],
                'venue_name' => 'HQ Boardroom',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'online_meeting_url' => 'https://meet.google.com/boardroom-q4',
                'schedule_type' => 'single',
                'schedule_timezone' => 'Africa/Lagos',
                'start_date' => now()->addDays(40)->toDateString(),
                'start_time' => '10:00',
                'end_date' => now()->addDays(40)->toDateString(),
                'end_time' => '14:00',
                'status' => EventStatus::DRAFT,
                'visibility' => EventVisibility::UNLISTED,
                'banner_url' => null,
                'is_featured' => false,
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
                'start_time' => '15:00',
                'end_date' => now()->addDays(14)->toDateString(),
                'end_time' => '18:00',
                'status' => EventStatus::PUBLISHED,
                'visibility' => EventVisibility::PUBLIC,
                'banner_url' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=1200&auto=format&fit=crop',
                'is_featured' => true,
            ],
        ];

        foreach ($events as $eventData) {
            Event::updateOrCreate(
                [
                    'organizer_id' => $organizer->id,
                    'slug' => Str::slug($eventData['title']),
                ],
                array_merge($eventData, [
                    'user_id' => $userId,
                ])
            );
        }
    }
}
