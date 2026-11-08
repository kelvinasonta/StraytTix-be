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
            [
                'slug' => 'send_invitation_link',
                'subject' => "You've been invited to join {{organizerName}} on {{siteName}}",
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #0f172a; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">You\'re Invited!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Join {{organizerName}} on {{siteName}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;"><strong>{{organizerName}}</strong> has invited you to join their team as a <strong>{{role}}</strong> on <strong>{{siteName}}</strong>.</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0;">As a {{role}}, you will be able to access the organizer dashboard, collaborate with team members, and help manage events.</p>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{invitation_link}}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Accept Invitation</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0 0 8px 0;">If the button above does not work, copy and paste this link into your browser:</p>
        <p style="margin: 0 0 16px 0; word-break: break-all;"><a href="{{invitation_link}}" style="color: #4f46e5; text-decoration: underline;">{{invitation_link}}</a></p>
        <p style="margin: 0 0 8px 0;">⏳ This invitation link will expire in <strong>{{expiry_time}}</strong>.</p>
        <p style="margin: 0;">If you were not expecting this invitation, you can safely disregard this email.</p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'resend_invitation_link',
                'subject' => 'Reminder: Your Invitation to Join {{organizerName}} on {{siteName}}',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <span style="display: inline-block; background-color: #e0e7ff; color: #4338ca; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Invitation Reminder</span>
        <h1 style="color: #0f172a; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">Your New Invitation Link</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Join {{organizerName}} on {{siteName}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">A new invitation link has been generated for you to join <strong>{{organizerName}}</strong> as a <strong>{{role}}</strong> on <strong>{{siteName}}</strong>.</p>
        <p style="font-size: 14px; line-height: 1.6; margin: 0; color: #b45309; background-color: #fef3c7; padding: 10px 14px; border-radius: 4px;">⚠️ <strong>Note:</strong> Any previous invitation link sent to you has been invalidated for security. Please use the button below to join.</p>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{invitation_link}}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Accept Invitation</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0 0 8px 0;">If the button above does not work, copy and paste this link into your browser:</p>
        <p style="margin: 0 0 16px 0; word-break: break-all;"><a href="{{invitation_link}}" style="color: #4f46e5; text-decoration: underline;">{{invitation_link}}</a></p>
        <p style="margin: 0 0 8px 0;">⏳ This new link is valid for <strong>{{expiry_time}}</strong>.</p>
        <p style="margin: 0;">If you already accepted this invitation or believe this email was sent in error, no further action is required.</p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'event_staff_invitation',
                'subject' => "You're Invited to Join {{organizerName}} on {{siteName}}",
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #0f172a; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">You\'re Invited!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Join {{organizerName}} on {{siteName}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;"><strong>{{organizerName}}</strong> has invited you to join their team as a <strong>{{role}}</strong> on <strong>{{siteName}}</strong>.</p>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{invitation_link}}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px;">Accept Invitation</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0 0 8px 0;"><a href="{{invitation_link}}" style="color: #4f46e5;">{{invitation_link}}</a></p>
        <p style="margin: 0 0 8px 0;">This invitation will expire after {{expiry_time}}.</p>
        <p style="margin: 0;">Best regards,<br>The {{siteName}} Team</p>
    </div>
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
