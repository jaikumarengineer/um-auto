<?php

namespace Modules\Quality\Console;

use Illuminate\Console\Command;
use Modules\Quality\Services\QualityDigest;

class SendSampleQualityEmails extends Command
{
    protected $signature = 'quality:sample-emails
                            {email : Inbox that should receive every sample layout}
                            {--preview-only : Write HTML previews without sending mail}';

    protected $description = 'Send every quality alert email layout (with demo data) to one inbox for review';

    public function handle(QualityDigest $digest): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Provide a valid email address.');

            return self::FAILURE;
        }

        $sendMail = ! $this->option('preview-only');
        $mailer = (string) config('mail.default');

        $this->info($sendMail
            ? "Sending all sample layouts via [{$mailer}] to {$email}…"
            : 'Writing HTML previews only (no send)…');

        $alerts = $digest->sendSamples($email, sendMail: $sendMail, writePreviews: true);

        $sent = collect($alerts)->where('status', 'sent')->count();
        $failed = collect($alerts)->filter(fn ($alert) => filled(data_get($alert->meta, 'mail_error')))->count();

        $this->table(
            ['Type', 'Subject', 'Status'],
            collect($alerts)->map(fn ($alert) => [
                $alert->alert_type,
                $alert->subject,
                data_get($alert->meta, 'mail_error') ?: $alert->status,
            ])->all(),
        );

        $this->info('Logged '.count($alerts).' sample alerts. Sent: '.$sent.'. Errors: '.$failed.'.');
        $this->info('HTML previews: '.storage_path('app/email-samples'));

        if ($sendMail && $mailer === 'log') {
            $this->warn('MAIL_MAILER is currently "log". Messages were written to the Laravel log, not a real inbox.');
            $this->warn('Set SMTP under Settings → Mail (or in .env), then run this command again.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
