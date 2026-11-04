<?php

namespace Modules\Notification\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Notification\app\Models\EmailSettings;
use Modules\Notification\app\Models\EmailTemplate;

class NotificationDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $emailTemplates = [
            [
                'slug' => 'user_verification',
                'subject' => 'Account Verification',
                'body' => '<div>
                            <h2>Verify Your Email Address</h2>
                            <p>Hello {{name}},</p>
                            <p> Thanks for creating an account with {{siteName}}. Please verify your email address to activate your account. </p>
                            <p> <a href="{{verification_link}}">Verify My Email</a> </p>
                            <p> <a href="{{verification_link}}">{{verification_link}}</a> </p>
                            <p> This verification link will expire after {{expiry_time}}. </p>
                            <p> If you did not create this account, you can safely ignore this email. </p>
                            <p> Best regards,<br> The {{siteName}} Team </p>
                        </div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'welcome',
                'subject' => 'Welcome {{siteName}}',
                'body' => '<div>
                            <h2>Welcome, {{name}}!</h2>
                            <p>We\'re excited to have you with us.</p>
                            <p> Your account has been successfully created, and you\'re now ready to explore everything {{siteName}} has to offer. </p>
                            <p> If you have any questions, feel free to reach out to our support team. </p>
                            <p>Thanks for joining us!</p>
                            <p> Best regards,<br> The {{siteName}} Team </p>
                        </div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'resend_verification_email',
                'subject' => 'Your New Email Verification Link',
                'body' => '<div>
        <h2>Verify Your Email Address</h2>
        <p>Hello {{name}},</p>
        <p>You requested a new verification link for your {{siteName}} account.</p>
        <p><a href="{{verification_link}}">Verify My Email</a></p>
        <p><a href="{{verification_link}}">{{verification_link}}</a></p>
        <p>This verification link will expire after {{expiry_time}}.</p>
        <p>If you did not request a new verification link, you can safely ignore this email.</p>
        <p>Best regards,<br>The {{siteName}} Team</p>
    </div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'forgot_password',
                'subject' => 'Reset Your Password',
                'body' => '<div>
        <h2>Reset Your Password</h2>
        <p>Hello {{name}},</p>
        <p>You requested to reset your password for your {{siteName}} account.</p>
        <p><a href="{{reset_link}}">Reset Password</a></p>
        <p><a href="{{reset_link}}">{{reset_link}}</a></p>
        <p>This password reset link will expire after {{expiry_time}}.</p>
        <p>If you did not request a password reset, no further action is required.</p>
        <p>Best regards,<br>The {{siteName}} Team</p>
    </div>',
                'type' => 'html',
                'is_active' => true,
            ],
        ];

        $emailSettings = [
            [
                'key' => 'email_header',
                'value' => '<!DOCTYPE html>
                            <html lang="en">

                            <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>{{subject}}</title>
                            </head>

                            <body>',
                'is_active' => true,
            ],
            [
                'key' => 'email_footer',
                'value' => '<footer>
                            <p>&copy; 2026 [Company Name]. All rights reserved.</p>
                            <p>This is an automated email. Please do not reply to this message.</p>
                        </footer>
                        </body>

                        </html>',
                'is_active' => true,
            ],
        ];

        foreach ($emailTemplates as $template) {
            EmailTemplate::updateOrCreate(['slug' => $template['slug']], $template);
        }

        foreach ($emailSettings as $emailSetting) {
            EmailSettings::updateOrCreate(['key' => $emailSetting['key']], $emailSetting);
        }
    }
}
