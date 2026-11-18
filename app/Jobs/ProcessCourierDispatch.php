<?php

namespace App\Jobs;

use App\Models\CourierDispatchRequest;
use App\Services\CourierDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCourierDispatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(public readonly int $dispatchRequestId) {}

    public function handle(CourierDispatchService $service): void
    {
        $service->process($this->dispatchRequestId);
    }

    public function failed(Throwable $exception): void
    {
        $reason = trim((string) preg_replace('/\s+/', ' ', strip_tags($exception->getMessage())));

        CourierDispatchRequest::query()
            ->whereKey($this->dispatchRequestId)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($reason !== '' ? $reason : 'Dispatch processing failed.', 0, 1000),
                'updated_at' => now(),
            ]);
    }
}
