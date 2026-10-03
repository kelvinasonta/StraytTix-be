<?php

declare(strict_types=1);

namespace Modules\Events\app\DTOs;

class EventSettingDTO
{
    /**
     * @param  array<string, mixed>|null  $amenities
     * @param  array<int, mixed>|null  $faqs
     * @param  array<int, string>|null  $gallery_urls
     */
    public function __construct(
        public readonly ?string $stream_provider = null,
        public readonly ?string $stream_instructions = null,
        public readonly ?string $refund_policy = null,
        public readonly ?string $refund_option = null,
        public readonly ?string $age_restriction = null,
        public readonly ?string $dress_code = null,
        public readonly ?string $event_rules = null,
        public readonly ?array $amenities = null,
        public readonly ?string $confirmation_email_msg = null,
        public readonly ?string $status_message = null,
        public readonly bool $is_status_msg_enabled = false,
        public readonly ?string $status_message_type = null,
        public readonly ?string $pre_sale_message = null,
        public readonly ?string $post_sale_message = null,
        public readonly ?string $theme_color = null,
        public readonly ?string $banner_style = null,
        public readonly ?array $faqs = null,
        public readonly ?array $gallery_urls = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Support nested settings array if present
        $settingsData = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : [];
        $merged = array_merge($settingsData, $data);

        $faqs = $merged['faqs'] ?? null;
        $galleryUrls = $merged['galleryUrls'] ?? $merged['gallery'] ?? $merged['gallery_urls'] ?? null;

        return new self(
            stream_provider: $merged['streamProvider'] ?? $merged['stream_provider'] ?? null,
            stream_instructions: $merged['streamInstructions'] ?? $merged['stream_instructions'] ?? null,
            refund_policy: $merged['refundPolicy'] ?? $merged['refund_policy'] ?? null,
            refund_option: $merged['refundOption'] ?? $merged['refund_option'] ?? null,
            age_restriction: $merged['ageRestriction'] ?? $merged['age_restriction'] ?? null,
            dress_code: $merged['dressCode'] ?? $merged['dress_code'] ?? null,
            event_rules: $merged['eventRules'] ?? $merged['event_rules'] ?? null,
            amenities: $merged['amenities'] ?? null,
            confirmation_email_msg: $merged['confirmationEmailMsg'] ?? $merged['confirmation_email_msg'] ?? 'Thank you for your purchase! We appreciate your support and look forward to seeing you at the event.',
            status_message: $merged['statusMessage'] ?? $merged['status_message'] ?? null,
            is_status_msg_enabled: (bool) ($merged['isStatusMsgEnabled'] ?? $merged['is_status_msg_enabled'] ?? false),
            status_message_type: $merged['statusMessageType'] ?? $merged['status_message_type'] ?? null,
            pre_sale_message: $merged['preSaleMessage'] ?? $merged['pre_sale_message'] ?? 'Tickets will go on sale soon.',
            post_sale_message: $merged['postSaleMessage'] ?? $merged['post_sale_message'] ?? 'Ticket sales have ended. Thank you for your interest and support!',
            theme_color: $merged['themeColor'] ?? $merged['theme_color'] ?? null,
            banner_style: $merged['bannerStyle'] ?? $merged['banner_style'] ?? null,
            faqs: $faqs,
            gallery_urls: $galleryUrls,
        );
    }

    public function toArray(): array
    {
        return [
            'stream_provider' => $this->stream_provider,
            'stream_instructions' => $this->stream_instructions,
            'refund_policy' => $this->refund_policy,
            'refund_option' => $this->refund_option,
            'age_restriction' => $this->age_restriction,
            'dress_code' => $this->dress_code,
            'event_rules' => $this->event_rules,
            'amenities' => $this->amenities,
            'confirmation_email_msg' => $this->confirmation_email_msg,
            'status_message' => $this->status_message,
            'is_status_msg_enabled' => $this->is_status_msg_enabled,
            'status_message_type' => $this->status_message_type,
            'pre_sale_message' => $this->pre_sale_message,
            'post_sale_message' => $this->post_sale_message,
            'theme_color' => $this->theme_color,
            'banner_style' => $this->banner_style,
            'faqs' => $this->faqs,
            'gallery_urls' => $this->gallery_urls,
        ];
    }
}
