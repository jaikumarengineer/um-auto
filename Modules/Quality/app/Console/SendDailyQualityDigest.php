<?php

namespace Modules\Quality\Console;

use Illuminate\Console\Command;
use Modules\Quality\Services\QualityDigest;

class SendDailyQualityDigest extends Command
{
    protected $signature = 'quality:digest {cadence=daily : daily, weekly, or monthly} {--date=} {--no-mail}';

    protected $description = 'Email overdue, pending, completed, and summary alerts for PO vs PI and incoming inspection';

    protected $aliases = ['quality:daily-digest'];

    public function handle(QualityDigest $digest): int
    {
        $cadence = (string) $this->argument('cadence');

        if (! in_array($cadence, ['daily', 'weekly', 'monthly'], true)) {
            $this->error('Cadence must be daily, weekly, or monthly.');

            return self::FAILURE;
        }

        $alerts = $digest->generate(
            $this->option('date'),
            sendMail: ! $this->option('no-mail'),
            cadence: $cadence,
        );

        $this->info('Logged '.count($alerts).' '.$cadence.' quality alerts.');

        return self::SUCCESS;
    }
}
