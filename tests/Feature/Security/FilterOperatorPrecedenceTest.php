<?php

namespace Tests\Feature\Security;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\User;
use App\Services\DrivingForceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterOperatorPrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_search_does_not_leak_records_with_unmatched_status_or_dimension(): void
    {
        $user = User::factory()->create();
        $env = \App\Models\Environment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Internal',
        ]);
        $dimension1 = Dimension::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Technology',
            'environment_id' => $env->id,
        ]);
        $dimension2 = Dimension::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        // Record A matches dimension 1, status CLOSED, description contains "quantum"
        $closedDf = DrivingForce::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'dimension_id' => $dimension1->id,
            'created_by' => $user->id,
            'keyword' => 'Computing',
            'description' => 'quantum processing unit',
            'status' => 'CLOSED',
        ]);

        // Record B is dimension 2, status PENDING (Draft), keyword contains "quantum"
        $draftDf = DrivingForce::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'dimension_id' => $dimension2->id,
            'created_by' => $user->id,
            'keyword' => 'quantum secret',
            'description' => 'encryption draft',
            'status' => 'PENDING',
        ]);

        $service = new DrivingForceService();

        // Query closed items filtered specifically for dimension 1 with search term "quantum"
        $result = $service->closedItems([
            'dimension' => $dimension1->id,
            'search' => 'quantum',
            'date' => ['2020-01-01', '2030-12-31'],
        ]);

        $ids = collect($result->resource->items())->pluck('id')->all();

        // Must ONLY return matching items from dimension 1 that are CLOSED
        $this->assertNotContains($draftDf->id, $ids, 'Draft record was leaked due to ungrouped OR query precedence!');
    }

    public function test_filter_does_not_leak_records_with_unmatched_status_or_dimension(): void
    {
        $user = User::factory()->create();
        $env = \App\Models\Environment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Internal',
        ]);
        $dimension1 = Dimension::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Technology',
            'environment_id' => $env->id,
        ]);
        $dimension2 = Dimension::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $closedDf = DrivingForce::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'dimension_id' => $dimension1->id,
            'created_by' => $user->id,
            'keyword' => 'Computing',
            'description' => 'quantum processing unit',
            'status' => 'CLOSED',
        ]);

        $draftDf = DrivingForce::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'dimension_id' => $dimension2->id,
            'created_by' => $user->id,
            'keyword' => 'quantum secret',
            'description' => 'encryption draft',
            'status' => 'PENDING',
        ]);

        $service = new DrivingForceService();

        $result = $service->filter([
            'dimension' => $dimension1->id,
            'status' => 'CLOSED',
            'search' => 'quantum',
            'date' => ['2020-01-01', '2030-12-31'],
        ]);

        $ids = collect($result->resource->items())->pluck('id')->all();

        $this->assertContains($closedDf->id, $ids);
        $this->assertNotContains($draftDf->id, $ids, 'Draft record was leaked due to ungrouped OR query precedence!');
    }
}
