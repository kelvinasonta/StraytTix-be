<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\app\Mail\GenericMail;
use Modules\Notification\database\seeders\NotificationDatabaseSeeder;
use Modules\Organizer\app\Services\Mail\SendOrganizerInvitationEmailService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('send invitation email uses send_invitation_link template and replaces placeholders', function () {
    $this->seed(NotificationDatabaseSeeder::class);
    Mail::fake();

    /** @var SendOrganizerInvitationEmailService $service */
    $service = app(SendOrganizerInvitationEmailService::class);

    $service->execute([
        'email' => 'candidate@example.com',
        'organizer_name' => 'Acme Global',
        'role' => 'manager',
        'invitation_token' => 'sample_token_123',
        'expires_at' => now()->addDays(7),
        'is_resend' => false,
    ]);

    Mail::assertSent(GenericMail::class, function (GenericMail $mail) {
        return $mail->hasTo('candidate@example.com')
            && str_contains($mail->renderedSubject, 'Acme Global')
            && str_contains($mail->renderedBody, 'Acme Global')
            && str_contains($mail->renderedBody, 'Manager')
            && str_contains($mail->renderedBody, 'sample_token_123');
    });
});

test('resend invitation email uses resend_invitation_link template with reminder styling', function () {
    $this->seed(NotificationDatabaseSeeder::class);
    Mail::fake();

    /** @var SendOrganizerInvitationEmailService $service */
    $service = app(SendOrganizerInvitationEmailService::class);

    $service->execute([
        'email' => 'candidate@example.com',
        'organizer_name' => 'Acme Global',
        'role' => 'finance',
        'invitation_token' => 'sample_new_token_456',
        'expires_at' => now()->addDays(7),
        'is_resend' => true,
    ]);

    Mail::assertSent(GenericMail::class, function (GenericMail $mail) {
        return $mail->hasTo('candidate@example.com')
            && str_contains($mail->renderedSubject, 'Reminder')
            && str_contains($mail->renderedBody, 'Acme Global')
            && str_contains($mail->renderedBody, 'Finance')
            && str_contains($mail->renderedBody, 'sample_new_token_456')
            && str_contains($mail->renderedBody, 'Invitation Reminder');
    });
});
