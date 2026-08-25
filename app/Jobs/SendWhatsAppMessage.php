<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $phone,
        public ?string $message = null,
        public ?string $documentUrl = null,
        public ?string $fileName = null,
        public ?string $caption = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        if ($this->documentUrl) {
            $whatsAppService->sendDocument($this->phone, $this->documentUrl, $this->fileName ?? 'document.pdf', $this->caption ?? '');
        } elseif ($this->message) {
            $whatsAppService->sendMessage($this->phone, $this->message);
        }
    }
}
