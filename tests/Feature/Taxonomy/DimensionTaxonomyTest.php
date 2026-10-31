<?php

namespace Tests\Feature\Taxonomy;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use App\Services\Extraction\LlmSignalExtractor;
use App\Services\SignalExtractionService;
use App\Support\DimensionTaxonomy;
use Database\Seeders\DefaultSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

/**
 * Roadmap R0.3: the driving force taxonomy follows the eleven categories of the
 * scenario planning method (macro: Political, Economic, Social, Technology,
 * Ecology, Legislative; mezzo: Consumer, Supply Chain, Competitor, New Entrant,
 * Substitute). See docs/product/SCENARIO_PLANNING_METHODOLOGY.md, step 2.
 */
class DimensionTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private const MACRO = ['Political', 'Economic', 'Social', 'Technology', 'Ecology', 'Legislative'];

    private const MEZZO = ['Consumer', 'Supply Chain', 'Competitor', 'New Entrant', 'Substitute'];

    public function test_seeded_taxonomy_has_the_eleven_method_categories_in_order(): void
    {
        $this->seed(DefaultSystemSeeder::class);

        $byEnvironment = Dimension::ordered()->with('environment')->get()->groupBy(fn ($d) => $d->environment->name);

        $this->assertSame(self::MACRO, $byEnvironment['Macro']->pluck('name')->all());
        $this->assertSame(self::MEZZO, $byEnvironment['Mezzo']->pluck('name')->all());
        $this->assertSame(11, Dimension::whereNotNull('code')->distinct()->count('code'));
    }

    public function test_seeding_twice_creates_no_duplicates(): void
    {
        $this->seed(DefaultSystemSeeder::class);
        $this->seed(DefaultSystemSeeder::class);

        $this->assertSame(11, Dimension::count());
        $this->assertSame(2, Environment::count());
    }

    public function test_existing_taxonomy_is_renamed_and_completed_without_losing_forces(): void
    {
        $macro = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $mezzo = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mezzo']);
        $old = [];
        foreach (['Economy' => $macro, 'Ecology' => $macro, 'Regulation' => $macro, 'Technology' => $macro,
            'Substitute' => $mezzo, 'Supplier' => $mezzo, 'Customer' => $mezzo, 'Competitor' => $mezzo] as $name => $env) {
            $old[$name] = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => $name, 'environment_id' => $env->id]);
        }
        $owner = User::factory()->create();
        $force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $old['Regulation']->id, 'created_by' => $owner->id,
            'keyword' => 'TKDN Regulation', 'description' => 'Local content rule.',
        ]);

        DimensionTaxonomy::align();

        // Renamed in place: the same rows, so forces and signals keep their category.
        $this->assertSame('Legislative', $old['Regulation']->fresh()->name);
        $this->assertSame('Economic', $old['Economy']->fresh()->name);
        $this->assertSame('Consumer', $old['Customer']->fresh()->name);
        $this->assertSame('Supply Chain', $old['Supplier']->fresh()->name);
        $this->assertSame('Legislative', $force->fresh()->dimension->name);

        $this->assertSame(11, Dimension::count());
        foreach (['Political', 'Social'] as $name) {
            $this->assertSame($macro->id, Dimension::where('name', $name)->value('environment_id'));
        }
        $this->assertSame($mezzo->id, Dimension::where('name', 'New Entrant')->value('environment_id'));
    }

    public function test_align_leaves_an_empty_taxonomy_empty_unless_asked_to_create(): void
    {
        DimensionTaxonomy::align(createWhenEmpty: false);

        $this->assertSame(0, Dimension::count());
    }

    public function test_ai_extraction_offers_the_eleven_categories(): void
    {
        $this->seed(DefaultSystemSeeder::class);
        $llm = (new FakeLlmClient)->respond(['is_relevant' => true, 'relevance_reason' => 'Relevan.', 'signals' => []]);

        (new LlmSignalExtractor($llm))->extract('Brief', 'The ministry issued a regulation on electric vehicles.');

        $enum = $llm->lastRequest()->schema['properties']['signals']['items']['properties']['dimension']['enum'];
        $this->assertSame([...self::MACRO, ...self::MEZZO], $enum);
    }

    public function test_keyword_rules_recognise_the_new_categories(): void
    {
        $this->seed(DefaultSystemSeeder::class);
        $service = app(SignalExtractionService::class);
        $detect = fn (string $text) => (fn () => $this->detectDimension($text, Dimension::ordered()->get()))->call($service)?->name;

        $this->assertSame('New Entrant', $detect('Three newcomers announced market entry with new players launching plants.'));
        $this->assertSame('Social', $detect('Lifestyle and culture shifts among Gen Z change social expectations across society.'));
        $this->assertSame('Political', $detect('The election and a geopolitical dispute pushed the minister to sanction imports.'));
        $this->assertSame('Legislative', $detect('The new law and regulation require legal compliance with the directive.'));
    }
}
