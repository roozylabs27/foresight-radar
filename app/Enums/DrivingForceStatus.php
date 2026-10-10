<?php

namespace App\Enums;

use App\Exceptions\InvalidStateTransitionException;

enum DrivingForceStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CLOSED = 'CLOSED';

    /**
     * Get legal transition targets from the current status.
     *
     * @return array<DrivingForceStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::APPROVED, self::REJECTED, self::CLOSED],
            self::APPROVED => [self::CLOSED, self::REJECTED, self::PENDING],
            self::REJECTED => [self::PENDING],
            self::CLOSED => [self::PENDING],
        };
    }

    /**
     * Determine whether transitioning to target status is valid.
     */
    public function canTransitionTo(self|string $target): bool
    {
        $targetEnum = is_string($target) ? self::tryFrom($target) : $target;

        if ($targetEnum === null) {
            return false;
        }

        // Staying in same status is idempotent and allowed
        if ($this === $targetEnum) {
            return true;
        }

        return in_array($targetEnum, $this->allowedTransitions(), true);
    }

    /**
     * Assert that transition to target status is valid; throw exception if not.
     *
     * @throws InvalidStateTransitionException
     */
    public function validateTransitionTo(self|string $target): self
    {
        $targetEnum = is_string($target) ? self::tryFrom($target) : $target;

        if ($targetEnum === null || !$this->canTransitionTo($targetEnum)) {
            $targetValue = is_string($target) ? $target : ($targetEnum?->value ?? 'UNKNOWN');
            throw new InvalidStateTransitionException(
                "Illegal driving force status transition from {$this->value} to {$targetValue}."
            );
        }

        return $targetEnum;
    }
}
