<?php

namespace Tests\Feature\Reliability;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Production readiness PRD-04: failed queue jobs used to be visible only in the `failed_jobs`
 * table. An hourly check logs them as an error and, when OPS_ALERT_EMAIL is set, mails a summary.
 */
class FailedJobsAlertTest extends TestCase
{
    use RefreshDatabase;

    private function failedJob(string $job, string $at): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => "App\\Jobs\\{$job}"]),
            'exception' => "RuntimeException: {$job} broke in /var/www/app/Jobs/{$job}.php:10",
            'failed_at' => $at,
        ]);
    }

    public function test_failures_of_the_last_hour_are_logged_as_an_error(): void
    {
        Log::spy();
        $this->failedJob('ExtractSignals', now()->subMinutes(20)->toDateTimeString());
        $this->failedJob('ExtractSignals', now()->subMinutes(5)->toDateTimeString());
        $this->failedJob('FetchIndicatorSource', now()->subMinutes(10)->toDateTimeString());
        $this->failedJob('MatchSignals', now()->subHours(3)->toDateTimeString());

        $this->artisan('ops:failed-jobs')
            ->expectsOutputToContain('3 queued job(s) failed')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $message === 'Queued jobs failed'
            && $context['count'] === 3 && $context['jobs'] === ['ExtractSignals' => 2, 'FetchIndicatorSource' => 1]);
    }

    public function test_no_recent_failures_stays_quiet(): void
    {
        Log::spy();
        $this->failedJob('MatchSignals', now()->subHours(3)->toDateTimeString());

        $this->artisan('ops:failed-jobs')->expectsOutputToContain('No queued job failed')->assertExitCode(0);

        Log::shouldNotHaveReceived('error');
    }

    public function test_the_mail_goes_to_the_configured_address_only(): void
    {
        config(['mail.default' => 'array', 'ops.alert_email' => 'ops@example.test']);
        $this->failedJob('ExtractSignals', now()->subMinutes(5)->toDateTimeString());

        $this->artisan('ops:failed-jobs')->assertExitCode(1);

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('ops@example.test', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertStringContainsString('ExtractSignals', $sent->first()->getOriginalMessage()->getTextBody());

        config(['ops.alert_email' => null]);
        $this->artisan('ops:failed-jobs')->assertExitCode(1);
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_it_runs_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'ops:failed-jobs'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
