<?php

namespace Tests\Unit;

use App\Models\DrivingForceRating;
use App\Models\Priority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriorityCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_either_analysis_is_missing(): void
    {
        $rating = new DrivingForceRating();

        $rating->impact_analysis = null;
        $rating->uncertainty_analysis = null;
        $this->assertNull($rating->calculatePriority());

        $rating->impact_analysis = 7;
        $rating->uncertainty_analysis = null;
        $this->assertNull($rating->calculatePriority());

        $rating->impact_analysis = null;
        $rating->uncertainty_analysis = 8;
        $this->assertNull($rating->calculatePriority());
    }

    public function test_calculates_high_priority_when_both_scores_are_six_or_greater(): void
    {
        $high = Priority::create(['uuid' => fake()->uuid(), 'name' => 'High', 'color' => 'red']);

        $rating = new DrivingForceRating();
        $rating->impact_analysis = 6;
        $rating->uncertainty_analysis = 6;
        $this->assertSame($high->id, $rating->calculatePriority());

        $rating->impact_analysis = 10;
        $rating->uncertainty_analysis = 8;
        $this->assertSame($high->id, $rating->calculatePriority());
    }

    public function test_calculates_medium_priority_when_exactly_one_score_is_six_or_greater(): void
    {
        $medium = Priority::create(['uuid' => fake()->uuid(), 'name' => 'Medium', 'color' => 'yellow']);

        $rating = new DrivingForceRating();
        $rating->impact_analysis = 8;
        $rating->uncertainty_analysis = 4;
        $this->assertSame($medium->id, $rating->calculatePriority());

        $rating->impact_analysis = 3;
        $rating->uncertainty_analysis = 9;
        $this->assertSame($medium->id, $rating->calculatePriority());
    }

    public function test_calculates_low_priority_when_both_scores_are_under_six(): void
    {
        $low = Priority::create(['uuid' => fake()->uuid(), 'name' => 'Low', 'color' => 'green']);

        $rating = new DrivingForceRating();
        $rating->impact_analysis = 5;
        $rating->uncertainty_analysis = 5;
        $this->assertSame($low->id, $rating->calculatePriority());

        $rating->impact_analysis = 1;
        $rating->uncertainty_analysis = 2;
        $this->assertSame($low->id, $rating->calculatePriority());
    }

    public function test_uses_fallback_ids_when_database_records_are_absent(): void
    {
        $rating = new DrivingForceRating();

        $rating->impact_analysis = 8;
        $rating->uncertainty_analysis = 8;
        $this->assertSame(1, $rating->calculatePriority());

        $rating->impact_analysis = 8;
        $rating->uncertainty_analysis = 2;
        $this->assertSame(2, $rating->calculatePriority());

        $rating->impact_analysis = 2;
        $rating->uncertainty_analysis = 2;
        $this->assertSame(3, $rating->calculatePriority());
    }
}
