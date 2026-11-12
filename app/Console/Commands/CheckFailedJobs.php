<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Production readiness PRD-04: queued jobs that failed in the last hour are logged as one error
 * and, when `ops.alert_email` is set, mailed as a summary. Runs hourly from the scheduler.
 * The summary names jobs and counts only: payloads and exceptions can hold tenant data.
 */
class CheckFailedJobs extends Command
{
    protected $signature = 'ops:failed-jobs {--minutes=60 : Look back this many minutes}';

    protected $description = 'Report queued jobs that failed recently (log, and mail when OPS_ALERT_EMAIL is set)';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes($minutes))->get(['payload']);

        if ($failed->isEmpty()) {
            $this->info("No queued job failed in the last {$minutes} minutes.");

            return self::SUCCESS;
        }

        $jobs = $failed->countBy(fn ($row) => class_basename((string) (json_decode($row->payload, true)['displayName'] ?? 'unknown')))
            ->sortKeys()->all();
        $summary = collect($jobs)->map(fn (int $count, string $job) => "{$job}: {$count}")->implode(', ');
        $message = "{$failed->count()} queued job(s) failed in the last {$minutes} minutes ({$summary}).";

        Log::error('Queued jobs failed', ['count' => $failed->count(), 'jobs' => $jobs, 'minutes' => $minutes]);
        $this->error($message);
        $this->mail($message);

        return self::FAILURE;
    }

    private function mail(string $message): void
    {
        $to = trim((string) config('ops.alert_email'));
        if ($to === '') {
            return;
        }

        try {
            Mail::raw($message."\n\nDetails: php artisan queue:failed on the server.", fn ($mail) => $mail
                ->to($to)
                ->subject('['.config('app.name').'] Queued jobs failed'));
        } catch (Throwable $e) {
            Log::warning('Failed-jobs alert mail not sent', ['error' => $e->getMessage()]);
        }
    }
}
