<?php

namespace App\Console\Commands;

use App\Jobs\SendNewsletterJob;
use App\Models\Newsletter;
use Illuminate\Console\Command;

class SendScheduledNewslettersCommand extends Command
{
    protected $signature = 'newsletter:send-scheduled';
    protected $description = 'Envia newsletters agendadas que já passaram da hora programada';

    public function handle(): int
    {
        $newsletters = Newsletter::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($newsletters->isEmpty()) {
            $this->info('Nenhuma newsletter agendada para envio.');
            return self::SUCCESS;
        }

        foreach ($newsletters as $newsletter) {
            $this->info("Enviando newsletter: {$newsletter->title}");
            SendNewsletterJob::dispatchSync($newsletter->id);
        }

        $this->info("Total de newsletters despachadas: {$newsletters->count()}");

        return self::SUCCESS;
    }
}
