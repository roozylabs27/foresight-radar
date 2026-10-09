<?php

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_indexes_exist_on_driving_forces_table(): void
    {
        $this->assertTrue(
            Schema::hasIndex('driving_forces', 'idx_df_status'),
            'Index idx_df_status must exist on driving_forces table'
        );

        $this->assertTrue(
            Schema::hasIndex('driving_forces', 'idx_df_status_created'),
            'Index idx_df_status_created must exist on driving_forces table'
        );

        $this->assertTrue(
            Schema::hasIndex('driving_forces', 'idx_df_dim_status'),
            'Index idx_df_dim_status must exist on driving_forces table'
        );
    }

    public function test_performance_indexes_exist_on_driving_force_ratings_table(): void
    {
        $this->assertTrue(
            Schema::hasIndex('driving_force_ratings', 'idx_dfr_created_at'),
            'Index idx_dfr_created_at must exist on driving_force_ratings table'
        );
    }
}
