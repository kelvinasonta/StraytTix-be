<?php

namespace Modules\Notification\app\Services;

use Modules\Notification\app\Cache\EmailTemplateCache;
use Modules\Notification\app\Models\EmailSettings;
use Modules\Notification\app\Models\EmailTemplate;

class EmailTemplateService
{
    public function __construct(
        protected EmailTemplateCache $emailTemplateCache,
    ) {}

    /**
     * Fetch a template by slug and replace {{placeholders}} with real data.
     *
     * @return array{header: string, subject: string, body: string, footer: string}
     */
    public function render(string $slug, array $data): array
    {
        $templateContent = $this->emailTemplateCache->remember($slug, function () use ($slug) {
            $template = EmailTemplate::where('slug', $slug)->first();

            return [
                'subject' => $template?->subject ?? '',
                'body' => $template?->body ?? '',
            ];
        });

        $templateSetting = $this->emailTemplateCache->rememberSetting(function () {
            $header = EmailSettings::where('key', 'email_header')->first();
            $footer = EmailSettings::where('key', 'email_footer')->first();

            return [
                'header_html' => $header?->value,
                'footer_html' => $footer?->value,
            ];
        });

        return [
            'header' => $this->replace($templateSetting['header_html'] ?? '', $data),
            'subject' => $this->replace($templateContent['subject'] ?? '', $data),
            'body' => $this->replace($templateContent['body'] ?? '', $data),
            'footer' => $this->replace($templateSetting['footer_html'] ?? '', $data),
        ];
    }

    protected function replace(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{'.$key.'}}', e($value), $content);
        }

        return $content;
    }
}
