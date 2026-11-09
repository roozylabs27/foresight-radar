<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.7: narratives as typed statements (fact / analysis / assumption / interpretation) and
 * citations as rows, so a signal's use in scenarios can be queried. `cited_signal_ids` stays filled
 * during the transition; existing citations are copied over without a statement kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->json('narrative_statements')->nullable()->after('narrative');
        });

        Schema::create('scenario_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('scenario_quadrant_id')->constrained('scenario_quadrants')->cascadeOnDelete();
            $table->foreignId('signal_id')->constrained('signals')->cascadeOnDelete();
            $table->string('ref', 10)->nullable();
            // fact, analysis, assumption, interpretation; null for untyped narratives
            $table->string('statement_kind', 20)->nullable();
            $table->unsignedSmallInteger('statement_index')->nullable();
            $table->timestamps();

            $table->index('signal_id');
        });

        $signals = DB::table('signals')->pluck('id')->flip();
        foreach (DB::table('scenario_quadrants')->whereNotNull('cited_signal_ids')->get(['id', 'tenant_id', 'cited_signal_ids']) as $quadrant) {
            foreach (array_unique((array) json_decode((string) $quadrant->cited_signal_ids, true)) as $signalId) {
                if (isset($signals[(int) $signalId])) {
                    DB::table('scenario_citations')->insert([
                        'tenant_id' => $quadrant->tenant_id,
                        'scenario_quadrant_id' => $quadrant->id,
                        'signal_id' => (int) $signalId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_citations');

        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->dropColumn('narrative_statements');
        });
    }
};
