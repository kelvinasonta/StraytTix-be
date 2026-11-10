<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\app\Cache\EmailTemplateCache;
use Modules\Notification\app\Mail\GenericMail;
use Modules\Notification\app\Models\EmailSettings;
use Modules\Notification\app\Models\EmailTemplate;
use Modules\Notification\app\Services\EmailTemplateService;
use Modules\Notification\app\Services\MailerService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    EmailSettings::create([
        'key' => 'email_header',
        'value' => '<header><h1>Header</h1></header>',
        'is_active' => true,
    ]);

    EmailSettings::create([
        'key' => 'email_footer',
        'value' => '<footer><p>Footer</p></footer>',
        'is_active' => true,
    ]);

    EmailTemplate::create([
        'slug' => 'test_email',
        'subject' => 'Hello {{name}}',
        'body' => '<p>Welcome to {{siteName}}!</p>',
        'type' => 'html',
        'is_active' => true,
    ]);
});

test('email template service renders template and substitutes placeholders', function () {
    /** @var EmailTemplateService $service */
    $service = app(EmailTemplateService::class);

    $rendered = $service->render('test_email', [
        'name' => 'Alex',
        'siteName' => 'StaytTix',
    ]);

    expect($rendered['subject'])->toBe('Hello Alex');
    expect($rendered['body'])->toContain('Welcome to StaytTix!');
    expect($rendered['header'])->toContain('Header');
    expect($rendered['footer'])->toContain('Footer');
});

test('mailer service renders and sends generic mail', function () {
    Mail::fake();

    /** @var MailerService $mailer */
    $mailer = app(MailerService::class);

    $sent = $mailer->send(
        to: 'user@example.com',
        slug: 'test_email',
        data: [
            'name' => 'Sarah',
            'siteName' => 'StaytTix',
        ],
        replyToEmail: 'support@strayttix.com',
        replyToName: 'Support'
    );

    expect($sent)->toBeTrue();

    Mail::assertSent(GenericMail::class, function (GenericMail $mail) {
        return $mail->hasTo('user@example.com')
            && $mail->renderedSubject === 'Hello Sarah'
            && str_contains($mail->renderedBody, 'Welcome to StaytTix!')
            && str_contains($mail->renderedBody, 'Header')
            && str_contains($mail->renderedBody, 'Footer');
    });
});

test('email template cache can store and forget templates', function () {
    /** @var EmailTemplateCache $cache */
    $cache = app(EmailTemplateCache::class);

    $cached = $cache->remember('custom_test', fn () => ['subject' => 'Custom Subject']);
    expect($cached['subject'])->toBe('Custom Subject');

    expect($cache->find('custom_test'))->toBe(['subject' => 'Custom Subject']);

    $forgotten = $cache->forget('custom_test');
    expect($forgotten)->toBeTrue();
    expect($cache->find('custom_test'))->toBeNull();
});
