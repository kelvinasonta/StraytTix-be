<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Modules\Auth\app\Models\User;
use Modules\Notification\app\Services\MailerService;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Enum\OrganizerVerificationStatus;
use Modules\Organizer\app\Events\OrganizerVerificationReviewedEvent;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerVerification;
use Modules\Organizer\app\Services\Mail\SendOrganizerVerificationEmailService;
use Modules\Organizer\app\Services\OrganizerVerificationService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('dispatches OrganizerVerificationReviewedEvent on approval and rejection', function () {
    EventFacade::fake([OrganizerVerificationReviewedEvent::class]);

    $admin = User::factory()->create([
        'is_admin' => true,
        'admin_role' => 'super_admin',
        'admin_permissions' => ['*'],
    ]);
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Acme Live Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $verification = OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'Acme Live Events Ltd',
        'business_type' => 'Company',
        'registration_number' => 'RC-123456',
        'tax_id' => 'TAX-654321',
        'official_email' => 'contact@acmelive.com',
        'official_phone' => '+2348012345678',
        'city' => 'Lagos',
        'country' => 'Nigeria',
    ]);

    $service = app(OrganizerVerificationService::class);

    // Test approve
    $service->approve($verification, $admin);

    EventFacade::assertDispatched(OrganizerVerificationReviewedEvent::class, function ($e) use ($verification, $admin) {
        return $e->verification->id === $verification->id
            && $e->reviewer->id === $admin->id
            && $e->status === 'verified';
    });

    // Test reject
    $service->reject($verification, $admin, 'Document expired');

    EventFacade::assertDispatched(OrganizerVerificationReviewedEvent::class, function ($e) use ($verification, $admin) {
        return $e->verification->id === $verification->id
            && $e->reviewer->id === $admin->id
            && $e->status === 'rejected'
            && $e->reason === 'Document expired';
    });
});

it('sends approval email to organizer', function () {
    $user = User::factory()->create(['email' => 'founder@acme.com', 'account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $user->id,
        'email' => 'founder@acme.com',
        'name' => 'Acme Entertainment',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $verification = OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $user->id,
        'status' => OrganizerVerificationStatus::VERIFIED,
        'business_name' => 'Acme Entertainment LLC',
        'business_type' => 'Company',
        'registration_number' => 'RC-999999',
        'city' => 'Lagos',
        'country' => 'Nigeria',
    ]);

    $mailerService = Mockery::mock(MailerService::class);
    $mailerService->shouldReceive('send')
        ->once()
        ->with(
            'founder@acme.com',
            'organizer_verification_approved',
            Mockery::on(function ($payload) {
                return $payload['organizerName'] === 'Acme Entertainment'
                    && $payload['businessName'] === 'Acme Entertainment LLC';
            })
        );

    $service = new SendOrganizerVerificationEmailService($mailerService);
    $service->execute($verification, 'verified');
});

it('sends rejection email with reason to organizer', function () {
    $user = User::factory()->create(['email' => 'founder@acme.com', 'account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $user->id,
        'email' => 'founder@acme.com',
        'name' => 'Acme Entertainment',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $verification = OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $user->id,
        'status' => OrganizerVerificationStatus::REJECTED,
        'business_name' => 'Acme Entertainment LLC',
        'business_type' => 'Company',
        'registration_number' => 'RC-999999',
        'city' => 'Lagos',
        'country' => 'Nigeria',
    ]);

    $mailerService = Mockery::mock(MailerService::class);
    $mailerService->shouldReceive('send')
        ->once()
        ->with(
            'founder@acme.com',
            'organizer_verification_rejected',
            Mockery::on(function ($payload) {
                return $payload['organizerName'] === 'Acme Entertainment'
                    && $payload['reason'] === 'CAC certificate was illegible.';
            })
        );

    $service = new SendOrganizerVerificationEmailService($mailerService);
    $service->execute($verification, 'rejected', 'CAC certificate was illegible.');
});
