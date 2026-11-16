<?php

namespace Tests\Feature\Reliability;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/** Production readiness 2026-11-13: `ops:alert` (delivery test and alerts from the backup script). */
class OpsAlertTest extends TestCase
{
    public function test_the_delivery_test_is_sent_to_the_alert_address_and_masks_it_in_output(): void
    {
        config(['ops.alert_email' => 'ops-team@example.com', 'mail.default' => 'array']);
        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent) {
            $sent[] = $event->message;
        });

        $this->artisan('ops:alert --test')
            ->expectsOutputToContain('o***@example.com')
            ->doesntExpectOutputToContain('ops-team@example.com')
            ->assertSuccessful();

        $this->assertCount(1, $sent);
        $this->assertSame('ops-team@example.com', $sent[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Alert delivery test', $sent[0]->getSubject());
        $this->assertStringContainsString('Delivery test of the operational alert path', $sent[0]->getTextBody());
    }

    public function test_an_alert_message_is_mailed_trimmed(): void
    {
        config(['ops.alert_email' => 'ops-team@example.com', 'mail.default' => 'array']);
        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent) {
            $sent[] = $event->message;
        });

        $this->artisan('ops:alert', ['message' => 'Backup failed: '.str_repeat('x', 2000)])->assertSuccessful();

        $this->assertStringContainsString('Backup failed:', $sent[0]->getTextBody());
        $this->assertLessThan(1200, strlen($sent[0]->getTextBody()));
    }

    public function test_without_an_alert_address_it_fails_loudly(): void
    {
        config(['ops.alert_email' => null]);
        Mail::fake();

        $this->artisan('ops:alert --test')->expectsOutputToContain('OPS_ALERT_EMAIL')->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_a_refusing_transport_is_logged_and_does_not_loop(): void
    {
        config(['ops.alert_email' => 'ops-team@example.com']);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Connection refused'));
        Log::spy();

        $this->artisan('ops:alert --test')->expectsOutputToContain('refused')->assertFailed();

        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'Ops alert mail not sent')->once();
    }
}
