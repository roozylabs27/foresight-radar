<?php

namespace Tests\Unit;

use App\Enums\DrivingForceStatus;
use App\Exceptions\InvalidStateTransitionException;
use PHPUnit\Framework\TestCase;

class DrivingForceStatusTest extends TestCase
{
    public function test_pending_allowed_transitions(): void
    {
        $status = DrivingForceStatus::PENDING;

        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::APPROVED));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::REJECTED));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::CLOSED));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::PENDING));
        $this->assertTrue($status->canTransitionTo('APPROVED'));

        $this->assertFalse($status->canTransitionTo('INVALID_STATUS'));
    }

    public function test_approved_allowed_transitions(): void
    {
        $status = DrivingForceStatus::APPROVED;

        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::CLOSED));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::REJECTED));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::PENDING));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::APPROVED));
    }

    public function test_rejected_can_only_transition_to_pending(): void
    {
        $status = DrivingForceStatus::REJECTED;

        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::PENDING));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::REJECTED));

        $this->assertFalse($status->canTransitionTo(DrivingForceStatus::APPROVED));
        $this->assertFalse($status->canTransitionTo(DrivingForceStatus::CLOSED));

        $this->expectException(InvalidStateTransitionException::class);
        $status->validateTransitionTo(DrivingForceStatus::APPROVED);
    }

    public function test_closed_can_only_transition_to_pending(): void
    {
        $status = DrivingForceStatus::CLOSED;

        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::PENDING));
        $this->assertTrue($status->canTransitionTo(DrivingForceStatus::CLOSED));

        $this->assertFalse($status->canTransitionTo(DrivingForceStatus::APPROVED));
        $this->assertFalse($status->canTransitionTo(DrivingForceStatus::REJECTED));

        $this->expectException(InvalidStateTransitionException::class);
        $status->validateTransitionTo(DrivingForceStatus::APPROVED);
    }

    public function test_validate_transition_returns_target_enum_when_valid(): void
    {
        $status = DrivingForceStatus::PENDING;
        $target = $status->validateTransitionTo(DrivingForceStatus::APPROVED);

        $this->assertSame(DrivingForceStatus::APPROVED, $target);
    }
}
