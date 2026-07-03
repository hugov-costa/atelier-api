<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PieceKind;
use App\Models\Clay;
use App\Models\FiringCycle;
use App\Models\Glaze;
use App\Models\Piece;
use App\Models\PieceCategory;
use App\Models\Setting;
use App\Models\User;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PieceService
{
    use PaginatesWithCache;

    /**
     * @var array<int, string>
     */
    private const RELATIONS = ['clay', 'glaze', 'category', 'user', 'firingCycles'];

    public function __construct(
        private PiecePricingService $pricing,
        private PieceChargeService $charges,
    ) {}

    /**
     * Create a piece, freezing the material prices, base cost and margin used so
     * the row stays a faithful historical record. A piece is unique and its cost
     * is never recomputed afterwards.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Piece
    {
        return DB::transaction(function () use ($attributes): Piece {
            $kind = PieceKind::from($this->asString($attributes['kind']));
            $settings = Setting::current();

            $clay = Clay::query()->where('ulid', $this->asString($attributes['clay_id']))->firstOrFail();
            $glaze = $this->resolveGlaze($attributes);
            $category = $this->resolveCategory($attributes);
            $user = User::query()->where('ulid', $this->asString($attributes['user_id']))->firstOrFail();

            $clayAmount = $this->asFloat($attributes['clay_amount']);
            $glazeAmount = $glaze !== null
                && array_key_exists('glaze_amount', $attributes)
                && $attributes['glaze_amount'] !== null
                ? $this->asFloat($attributes['glaze_amount'])
                : null;

            $firingCycles = $this->resolveFiringCycles($attributes['firing_cycle_ids'] ?? null);

            /** @var array<int, int> $firingPrices */
            $firingPrices = $firingCycles->map(fn (FiringCycle $cycle): int => $cycle->price_per_unit)->values()->all();

            $categoryMargin = $category !== null ? $category->profit_margin : null;

            $pricing = $this->pricing->calculate(
                $settings,
                $kind,
                $clay->price,
                $clayAmount,
                $glaze?->price,
                $glazeAmount,
                $firingPrices,
                $categoryMargin,
            );

            $isStudent = $kind === PieceKind::Student;

            $piece = Piece::create([
                'kind'              => $kind,
                'clay_id'           => $clay->id,
                'glaze_id'          => $glaze?->id,
                'piece_category_id' => $category?->id,
                'user_id'           => $user->id,
                'clay_amount'       => $clayAmount,
                'glaze_amount'      => $glazeAmount,
                'name'              => $this->asString($attributes['name']),
                'clay_unit_price'   => $clay->price,
                'glaze_unit_price'  => $glaze?->price,
                'base_cost'         => $isStudent ? 0 : $settings->base_cost,
                'profit_margin'     => $isStudent ? null : ($categoryMargin ?? $settings->default_profit_margin),
                'price'             => $pricing['price'],
                'production_cost'   => $pricing['production_cost'],
            ]);

            $this->syncFiringCycles($firingCycles, $piece);

            $this->charges->createForPiece($piece);

            return $piece->load(self::RELATIONS);
        });
    }

    public function delete(Piece $piece): void
    {
        $piece->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Piece>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = Piece::query()->with(self::RELATIONS);

        return $this->cachedPaginate(Piece::class, $query, $perPage, orderBy: ['created_at' => 'desc']);
    }

    public function restore(Piece $piece): Piece
    {
        $piece->restore();

        return $piece->load(self::RELATIONS);
    }

    /**
     * Update the piece's administrative fields only. Its composition and the
     * pricing snapshot are immutable — a piece is a one-off physical object, so
     * re-pricing it would rewrite history. To fix the composition, recreate it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Piece $piece): Piece
    {
        if (array_key_exists('name', $attributes)) {
            $piece->update(['name' => $this->asString($attributes['name'])]);
        }

        return $piece->load(self::RELATIONS);
    }

    private function asFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCategory(array $attributes): ?PieceCategory
    {
        if (! array_key_exists('piece_category_id', $attributes) || $attributes['piece_category_id'] === null) {
            return null;
        }

        return PieceCategory::query()->where('ulid', $this->asString($attributes['piece_category_id']))->firstOrFail();
    }

    /**
     * @return Collection<int, FiringCycle>
     */
    private function resolveFiringCycles(mixed $ulids): Collection
    {
        if (! is_array($ulids) || $ulids === []) {
            return new Collection;
        }

        return FiringCycle::query()
            ->whereIn('ulid', array_values(array_filter($ulids, 'is_string')))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveGlaze(array $attributes): ?Glaze
    {
        if (! array_key_exists('glaze_id', $attributes) || $attributes['glaze_id'] === null) {
            return null;
        }

        return Glaze::query()->where('ulid', $this->asString($attributes['glaze_id']))->firstOrFail();
    }

    /**
     * @param  Collection<int, FiringCycle>  $firingCycles
     */
    private function syncFiringCycles(Collection $firingCycles, Piece $piece): void
    {
        $pivot = $firingCycles->mapWithKeys(
            fn (FiringCycle $cycle): array => [$cycle->id => ['price' => $cycle->price_per_unit]]
        )->all();

        $piece->firingCycles()->sync($pivot);
    }
}
