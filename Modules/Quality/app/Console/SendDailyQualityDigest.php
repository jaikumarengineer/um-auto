<?php

namespace Modules\Quality\Console;

use Illuminate\Console\Command;
use Modules\Quality\Services\QualityDigest;

class SendDailyQualityDigest extends Command
{
    protected $signature = 'quality:daily-digest {--date=} {--no-mail}';

    protected $description = 'Build the day summary, PO vs PI, and inspection alerts';

    public function handle(QualityDigest $digest): int
    {
        $alerts = $digest->generate(
            $this->option('date'),
            sendMail: ! $this->option('no-mail'),
        );

        $this->info('Logged '.count($alerts).' quality alerts.');

        return self::SUCCESS;
    }
}
