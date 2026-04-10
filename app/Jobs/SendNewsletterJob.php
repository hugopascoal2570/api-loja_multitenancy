<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        private string $newsletterId
    ) {}

    public function handle(): void
    {
        $newsletter = Newsletter::find($this->newsletterId);

        if (!$newsletter || $newsletter->status === 'sent') {
            return;
        }

        $newsletter->update(['status' => 'sending']);

        $subscribers = NewsletterSubscriber::active()->get();
        $sentCount = 0;
        $failedCount = 0;

        foreach ($subscribers as $subscriber) {
            try {
                $unsubscribeUrl = rtrim(env('APP_API_URL', env('APP_URL')), '/') . '/api/newsletter/unsubscribe/' . $subscriber->unsubscribe_token;

                Mail::to($subscriber->email)
                    ->send(new NewsletterMail($newsletter, $unsubscribeUrl));

                $sentCount++;
            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Newsletter: Falha ao enviar para {$subscriber->email}", [
                    'newsletter_id' => $newsletter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $newsletter->update([
            'status' => $failedCount === $subscribers->count() ? 'failed' : 'sent',
            'sent_at' => now(),
            'total_recipients' => $sentCount,
        ]);

        Log::info("Newsletter '{$newsletter->title}' enviada", [
            'newsletter_id' => $newsletter->id,
            'sent' => $sentCount,
            'failed' => $failedCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $newsletter = Newsletter::find($this->newsletterId);

        if ($newsletter) {
            $newsletter->update(['status' => 'failed']);
        }

        Log::error("Newsletter Job falhou completamente", [
            'newsletter_id' => $this->newsletterId,
            'error' => $exception->getMessage(),
        ]);
    }
}
