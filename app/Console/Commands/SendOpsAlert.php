<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Production readiness 2026-11-13: one mail path for operational alerts.
 *
 *   php artisan ops:alert --test                     # end-to-end delivery test to OPS_ALERT_EMAIL
 *   php artisan ops:alert "Backup failed: ..."       # used by scripts/ops/backup-database.sh on failure
 *
 * The message is sent synchronously (not queued: an alert must not depend on the queue it may report).
 * Exit code 0 means the mail transport accepted the message; delivery to the inbox is confirmed by the
 * recipient. A failure is logged and returns 1; it never triggers another alert (no loop).
 * Callers pass operational text only (no payloads, keys or personal data); it is trimmed to 1,000 chars.
 */
class SendOpsAlert extends Command
{
    protected $signature = 'ops:alert {message? : The alert text} {--test : Send a delivery test instead}';

    protected $description = 'Mail an operational alert (or a delivery test) to OPS_ALERT_EMAIL';

    public function handle(): int
    {
        $to = trim((string) config('ops.alert_email'));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('OPS_ALERT_EMAIL is empty or not an e-mail address: no alert can be sent.');
            Log::error('Ops alert not sent: OPS_ALERT_EMAIL is not configured');

            return self::FAILURE;
        }

        $test = (bool) $this->option('test');
        $text = $test
            ? 'Delivery test of the operational alert path. If you read this, alerts reach this mailbox.'
            : Str::limit(trim((string) $this->argument('message')), 1000);
        if ($text === '') {
            $this->error('Give an alert message, or --test.');

            return self::FAILURE;
        }

        $sentAt = now()->toIso8601String();
        $subject = '['.config('app.name').'] '.($test ? 'Alert delivery test' : 'Operational alert');
        $body = $text."\n\nEnvironment: ".config('app.env')."\nTime: {$sentAt}\nSent by: php artisan ops:alert";

        try {
            Mail::raw($body, fn ($mail) => $mail->to($to)->subject($subject));
        } catch (Throwable $e) {
            Log::error('Ops alert mail not sent', ['test' => $test, 'error' => Str::limit($e->getMessage(), 300)]);
            $this->error('The mail transport refused the alert: '.Str::limit($e->getMessage(), 300));

            return self::FAILURE;
        }

        Log::info('Ops alert mail accepted by the transport', ['test' => $test, 'sent_at' => $sentAt]);
        $this->info("Accepted by the mail transport ({$sentAt}) for ".$this->mask($to).'. Check that mailbox to confirm delivery.');

        return self::SUCCESS;
    }

    /** a***@example.com: enough to recognise the mailbox without printing it in shared output. */
    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 1).'***@'.$domain;
    }
}
