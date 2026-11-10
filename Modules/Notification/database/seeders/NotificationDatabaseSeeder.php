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
                'slug' => 'event_reminder',
                'subject' => '⏰ Reminder: {{eventTitle}} is happening tomorrow!',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #4f46e5; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">⏰ Tomorrow is the Day!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Get ready for {{eventTitle}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello <strong>{{attendeeName}}</strong>,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 16px 0;">Just a quick reminder that you are attending <strong>"{{eventTitle}}"</strong> tomorrow! Here are your event access details:</p>
        <div style="margin: 16px 0; padding: 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; line-height: 1.8;">
            <div>📅 <strong>Date & Time:</strong> {{eventStartDate}}</div>
            <div>📍 <strong>Location:</strong> {{eventLocation}}</div>
            <div>🎫 <strong>Ticket Number:</strong> #{{ticketNumber}}</div>
        </div>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{viewTicketUrl}}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Show Ticket / QR Code</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0 0 8px 0;">💡 <strong>Pro Tip for Fast Entry:</strong> Have your QR code open on your phone screen before reaching the security checkpoint.</p>
        <p style="margin: 0;">See you at the event!</p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'organizer_verification_approved',
                'subject' => '🎉 Congratulations! Your organization {{organizerName}} has been verified',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #059669; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">🎉 You\'re Officially Verified!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">{{organizerName}} is now a verified organizer on {{siteName}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello <strong>{{name}}</strong>,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Our compliance team has reviewed and approved your organization\'s verification documents for <strong>{{businessName}}</strong>.</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0;">Your account now has full access to create paid ticketed events, request automated bank payouts, and display the verified organizer trust badge to attendees.</p>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{dashboardUrl}}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Go to Organizer Dashboard</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0;">Thank you for partnering with {{siteName}}. If you need any assistance setting up your events, our support team is always here to help.</p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Compliance Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'organizer_verification_rejected',
                'subject' => '⚠️ Action Required: Verification update for {{organizerName}}',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #dc2626; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">⚠️ Verification Update</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Review feedback for {{organizerName}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello <strong>{{name}}</strong>,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Our compliance team reviewed your verification submission for <strong>{{businessName}}</strong>, and unfortunately we were unable to approve it at this time.</p>
        <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 14px; margin: 16px 0; color: #991b1b; font-size: 14px; line-height: 1.6;">
            <strong>Reason provided:</strong><br>
            {{reason}}
        </div>
        <p style="font-size: 15px; line-height: 1.6; margin: 0;">Don\'t worry — you can easily update your information or upload valid document replacements using the button below.</p>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{resubmitUrl}}" style="display: inline-block; background-color: #dc2626; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Update Verification Documents</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0;">If you have any questions or feel this was an error, please reach out directly to support.</p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Compliance Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'order_confirmation',
                'subject' => '🎟️ Your tickets for {{eventTitle}} (#{{orderNumber}})',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #0f172a; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">🎉 You\'re Going!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Order #{{orderNumber}} confirmed</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hi <strong>{{customerName}}</strong>,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 16px 0;">Your order for <strong>"{{eventTitle}}"</strong> has been processed successfully. Below are your event and ticket details:</p>
        <div style="margin: 16px 0; padding: 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; line-height: 1.8;">
            <div>📅 <strong>Date:</strong> {{eventStartDate}}</div>
            <div>📍 <strong>Location:</strong> {{eventLocation}}</div>
            <div>💳 <strong>Total Paid:</strong> {{orderTotal}}</div>
            <div>🎟️ <strong>Tickets ({{ticketCount}}):</strong></div>
            <div style="margin-top: 10px;">{{ticketsListHtml}}</div>
        </div>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{downloadTicketsUrl}}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">View & Download Tickets</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0 0 8px 0;">💡 <strong>Gate Entry Tip:</strong> Have your QR codes ready on your mobile device or printed for fast gate check-in.</p>
        <p style="margin: 0;">Need help with your order? <a href="{{viewOrderUrl}}" style="color: #4f46e5; text-decoration: underline;">View Order Details</a></p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'organizer_ticket_sale',
                'subject' => '💰 New ticket sale for {{eventTitle}}! ({{orderTotal}})',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #059669; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">💰 Cha-ching! New Ticket Sale!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">You made a sale for {{eventTitle}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello <strong>{{organizerName}}</strong>,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 16px 0;">Great news! <strong>{{buyerName}}</strong> ({{buyerEmail}}) just purchased <strong>{{ticketCount}} ticket(s)</strong> for your event <strong>"{{eventTitle}}"</strong>.</p>
        <div style="margin: 16px 0; padding: 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; line-height: 1.8;">
            <div>📦 <strong>Order Number:</strong> #{{orderNumber}}</div>
            <div>💵 <strong>Order Amount:</strong> {{orderTotal}}</div>
            <div>🎟️ <strong>Quantity Sold:</strong> {{ticketCount}}</div>
        </div>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{viewOrderUrl}}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">View Order in Dashboard</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0;">Keep up the great work promoting your event!</p>
    </div>
    <div style="margin-top: 24px; font-size: 14px; color: #334155;">
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
            [
                'slug' => 'event_created',
                'subject' => 'Your event "{{eventTitle}}" has been created successfully!',
                'body' => '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1e293b; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #0f172a; font-size: 24px; font-weight: 700; margin: 0 0 8px 0;">🎉 Event Created!</h1>
        <p style="color: #64748b; font-size: 16px; margin: 0;">Your event is ready on {{siteName}}</p>
    </div>
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Hello <strong>{{creatorName}}</strong>,</p>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 0 12px 0;">Great news! Your event <strong>"{{eventTitle}}"</strong> for <strong>{{organizerName}}</strong> was created successfully.</p>
        <div style="margin: 16px 0; padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; line-height: 1.8;">
            <div>📅 <strong>Date:</strong> {{eventStartDate}}</div>
            <div>📍 <strong>Location:</strong> {{eventLocation}}</div>
            <div>📊 <strong>Status:</strong> {{eventStatus}}</div>
        </div>
    </div>
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{eventManageLink}}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">Manage Your Event</a>
    </div>
    <div style="font-size: 13px; color: #64748b; line-height: 1.6; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <p style="margin: 0 0 8px 0;">Need to add tickets or update schedule details? Click the button above to access your event dashboard.</p>
        <p style="margin: 0;">Best regards,<br><strong>The {{siteName}} Team</strong></p>
    </div>
</div>',
                'type' => 'html',
                'is_active' => true,
            ],
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
