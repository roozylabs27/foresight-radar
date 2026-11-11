<?php

namespace App\Services;

use App\Models\Dimension;
use App\Models\Environment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Plan part B2: an organisation customises its own dimensions and environments. Everything here is
 * tenant-scoped through the models. A code is generated once from the name and never changes (seed
 * data and AI prompts use it); anything still referenced is archived, not deleted.
 */
class DimensionService
{
    /** Tables whose rows point at a dimension. */
    private const USAGE_TABLES = ['driving_forces', 'signals', 'force_candidates'];

    /**
     * The settings page: environments in order, each with its dimensions in order and how many
     * forces, signals and candidates use them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function present(): array
    {
        $environments = Environment::ordered()->with(['dimensions' => fn ($q) => $q->ordered()])->get();
        $usage = $this->usage($environments->flatMap->dimensions->pluck('id')->all());

        return $environments->map(fn (Environment $environment) => [
            'id' => $environment->uuid,
            'name' => $environment->name,
            'dimensions' => $environment->dimensions->map(fn (Dimension $dimension) => [
                'id' => $dimension->uuid,
                'code' => $dimension->code,
                'name' => $dimension->name,
                'color' => $dimension->color,
                'archived' => $dimension->isArchived(),
                'in_use' => $usage[$dimension->id] ?? 0,
            ])->values()->all(),
        ])->values()->all();
    }

    /** @param  array{name: string, environment: Environment, color?: string|null}  $data */
    public function create(array $data): Dimension
    {
        $name = $this->cleanName($data['name']);
        $this->requireUniqueName($name);
        $environment = $data['environment'];

        return Dimension::create([
            'uuid' => (string) Uuid::uuid4(),
            'code' => $this->newCode($name),
            'name' => $name,
            'environment_id' => $environment->id,
            'sort_order' => (int) $environment->dimensions()->max('sort_order') + 1,
            'color' => $data['color'] ?? null,
        ]);
    }

    /** @param  array{name: string, environment: Environment, color?: string|null}  $data */
    public function update(Dimension $dimension, array $data): void
    {
        $name = $this->cleanName($data['name']);
        $this->requireUniqueName($name, $dimension);
        $environment = $data['environment'];

        $attributes = ['name' => $name, 'color' => $data['color'] ?? null];
        if ($environment->id !== $dimension->environment_id) {
            // Moved to another environment: it goes to the end there.
            $attributes += ['environment_id' => $environment->id, 'sort_order' => (int) $environment->dimensions()->max('sort_order') + 1];
        }
        $dimension->update($attributes);
    }

    /** Swap with the neighbour in the same environment. */
    public function move(Dimension $dimension, string $direction): void
    {
        $this->swap(Dimension::where('environment_id', $dimension->environment_id)->ordered()->get(), $dimension, $direction);
    }

    public function archive(Dimension $dimension, bool $archived): void
    {
        $dimension->update(['archived_at' => $archived ? now() : null]);
    }

    /** @throws ValidationException while forces, signals or candidates still use it */
    public function delete(Dimension $dimension): void
    {
        $inUse = $this->usage([$dimension->id])[$dimension->id] ?? 0;
        if ($inUse > 0) {
            throw ValidationException::withMessages(['dimension' => "Dimensi {$dimension->name} masih dipakai {$inUse} driving force, sinyal atau kandidat. Arsipkan saja agar tidak dipakai untuk data baru."]);
        }
        $dimension->delete();
    }

    public function createEnvironment(string $name): Environment
    {
        $name = $this->cleanName($name);
        $this->requireUniqueEnvironment($name);

        return Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => $name, 'sort_order' => (int) Environment::max('sort_order') + 1]);
    }

    public function renameEnvironment(Environment $environment, string $name): void
    {
        $name = $this->cleanName($name);
        $this->requireUniqueEnvironment($name, $environment);
        $environment->update(['name' => $name]);
    }

    public function moveEnvironment(Environment $environment, string $direction): void
    {
        $this->swap(Environment::ordered()->get(), $environment, $direction);
    }

    /** @throws ValidationException while it still holds dimensions (archived ones too) */
    public function deleteEnvironment(Environment $environment): void
    {
        if ($environment->dimensions()->exists()) {
            throw ValidationException::withMessages(['environment' => "Lingkungan {$environment->name} masih berisi dimensi. Pindahkan atau hapus dimensinya dulu."]);
        }
        $environment->delete();
    }

    /**
     * How many rows use each dimension, soft-deleted forces included (the foreign key still points at it).
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function usage(array $ids): array
    {
        $counts = [];
        foreach (self::USAGE_TABLES as $table) {
            foreach (DB::table($table)->whereIn('dimension_id', $ids)->selectRaw('dimension_id, count(*) as total')->groupBy('dimension_id')->pluck('total', 'dimension_id') as $id => $total) {
                $counts[$id] = ($counts[$id] ?? 0) + (int) $total;
            }
        }

        return $counts;
    }

    /**
     * Renumber the ordered rows 1..n with $item swapped against its neighbour.
     *
     * @param  Collection<int, Dimension>|Collection<int, Environment>  $ordered
     */
    private function swap(Collection $ordered, Model $item, string $direction): void
    {
        $rows = $ordered->values()->all();
        $index = collect($rows)->search(fn ($row) => $row->is($item));
        $other = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || ! isset($rows[$other])) {
            return;
        }
        [$rows[$index], $rows[$other]] = [$rows[$other], $rows[$index]];

        DB::transaction(function () use ($rows) {
            foreach ($rows as $position => $row) {
                $row->update(['sort_order' => $position + 1]);
            }
        });
    }

    private function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function requireUniqueName(string $name, ?Dimension $except = null): void
    {
        $taken = Dimension::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($except, fn ($q) => $q->whereKeyNot($except->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => "Dimensi {$name} sudah ada."]);
        }
    }

    private function requireUniqueEnvironment(string $name, ?Environment $except = null): void
    {
        $taken = Environment::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($except, fn ($q) => $q->whereKeyNot($except->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => "Lingkungan {$name} sudah ada."]);
        }
    }

    /** UPPER_SNAKE code from the name, unique in the organisation (AI_POLICY, AI_POLICY_2, …). */
    private function newCode(string $name): string
    {
        $base = Str::limit(Str::upper(Str::slug(Str::ascii($name), '_')), 26, '') ?: 'DIMENSION';
        $code = $base;
        for ($suffix = 2; Dimension::where('code', $code)->exists(); $suffix++) {
            $code = "{$base}_{$suffix}";
        }

        return $code;
    }
}
