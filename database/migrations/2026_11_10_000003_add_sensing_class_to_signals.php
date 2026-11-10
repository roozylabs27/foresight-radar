<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R4.2 (deck slide 26): a reviewer classifies a signal by horizon (likely: current trend,
 * could: current knowledge, might: weak signal / future knowledge) and may flag it as a wild card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->string('horizon', 8)->nullable()->after('review_status');
            $table->boolean('wild_card')->default(false)->after('horizon');
            $table->foreignId('classified_by')->nullable()->after('wild_card')
                ->constrained('users', indexName: 'signals_classified_by_fk')->nullOnDelete();
            $table->timestamp('classified_at')->nullable()->after('classified_by');

            $table->index(['review_status', 'horizon'], 'signals_review_horizon');
        });
    }

    public function down(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->dropIndex('signals_review_horizon');
            $table->dropForeign('signals_classified_by_fk');
            $table->dropColumn(['horizon', 'wild_card', 'classified_by', 'classified_at']);
        });
    }
};
