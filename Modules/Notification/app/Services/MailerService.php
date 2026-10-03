<?php

namespace Modules\Notification\app\Services;

use Illuminate\Support\Facades\Mail;
use Modules\Notification\app\Mail\GenericMail;

class MailerService
{
    public function __construct(
        protected EmailTemplateService $templateService,
    ) {}

    /**
     * Render a template by slug and send it to any recipient.
     */
    public function send(
        string $to,
        string $slug,
        array $data,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): bool {
        $rendered = $this->templateService->render($slug, $data);

        $fullBody = $this->wrapLayout($rendered);

        try {
            Mail::to($to)->send(new GenericMail(
                $rendered['subject'],
                $fullBody,
                $replyToEmail,
                $replyToName
            ));

            return true;
        } catch (\Throwable $e) {
            logger()->error("MailerService failed sending '{$slug}' to {$to}: ".$e->getMessage());

            throw $e;
        }
    }

    protected function wrapLayout(array $rendered): string
    {
        return <<<HTML
            {$rendered['header']}
            {$rendered['body']}
            {$rendered['footer']}             
        HTML;
    }
}
