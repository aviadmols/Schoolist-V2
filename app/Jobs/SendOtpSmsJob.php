<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\Sms\SmsService;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOtpSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    private string $phone;

    /** @var string */
    private string $code;

    /** @var int|null */
    private ?int $smsLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $code, ?int $smsLogId = null)
    {
        $this->phone = $phone;
        $this->code = $code;
        $this->smsLogId = $smsLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService): void
    {
        $message = $smsService->buildOtpMessage($this->code);
        $log = $this->smsLogId ? SmsLog::find($this->smsLogId) : null;

        if ($log) {
            $smsService->sendWithLog($log, $this->phone, $message);
            return;
        }

        $smsService->send($this->phone, $message);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        if (!$this->smsLogId) {
            return;
        }

        $log = SmsLog::find($this->smsLogId);
        if (!$log) {
            return;
        }

        $log->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'provider_response' => $this->buildFailureResponse($exception->getMessage()),
        ]);
    }

    /**
     * Build a provider response payload for failures.
     */
    private function buildFailureResponse(string $message): string
    {
        $payload = [
            'success' => false,
            'status_code' => null,
            'error' => $message,
            'body' => null,
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }
}
