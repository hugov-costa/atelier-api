<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\Glaze;
use App\Models\GlazeSupplier;
use App\Models\MaterialPurchase;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class MaterialPurchaseService
{
    /**
     * Set the linked material's current unit price to this receipt's unit price,
     * but only when this is the most recent receipt for the material — editing or
     * receiving an older, backdated purchase must not clobber a newer price. Pieces
     * snapshot their prices at creation, so past pieces are unaffected either way.
     */
    public function applyPriceFromReceipt(MaterialPurchase $purchase): void
    {
        $material = $this->concreteMaterial($purchase);

        if ($material === null || ! $this->isLatestReceipt($purchase)) {
            return;
        }

        $material->price = $purchase->unit_price;
        $material->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MaterialPurchase
    {
        $materialType = is_string($attributes['material_type'] ?? null) ? $attributes['material_type'] : '';
        $materialUlid = is_string($attributes['material_id'] ?? null) ? $attributes['material_id'] : '';

        $material = $this->resolveMaterial($materialType, $materialUlid);

        $supplierUlid = isset($attributes['supplier_id']) && is_string($attributes['supplier_id'])
            ? $attributes['supplier_id']
            : null;

        if ($supplierUlid !== null) {
            $supplier = $this->resolveSupplierByType($materialType, $supplierUlid);

            if ($supplier === null) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'The selected supplier is invalid.',
                ]);
            }
        } else {
            $supplier = $material instanceof Clay ? $material->claySupplier : $material->glazeSupplier;
        }

        unset($attributes['material_type'], $attributes['material_id'], $attributes['supplier_id']);

        $purchase = new MaterialPurchase($attributes);
        $purchase->material()->associate($material);
        $purchase->supplier()->associate($supplier);
        $purchase->save();

        if ($purchase->receipt_date !== null) {
            $this->applyPriceFromReceipt($purchase);
        }

        return $purchase->load(['material', 'supplier']);
    }

    public function delete(MaterialPurchase $purchase): void
    {
        $purchase->delete();
    }

    /**
     * @param  array{status?: string|null, material_type?: string|null, supplier_id?: string|null}  $filters
     * @return LengthAwarePaginator<int, MaterialPurchase>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $materialType = $filters['material_type'] ?? null;
        $supplierUlid = $filters['supplier_id'] ?? null;

        $materialType = is_string($materialType) && isset(MaterialPurchase::MATERIAL_TYPES[$materialType])
            ? $materialType
            : null;

        $supplierId = null;
        if ($supplierUlid !== null && $materialType !== null) {
            $supplier = $this->resolveSupplierByType($materialType, $supplierUlid);
            $supplierId = $supplier?->getKey();
        }

        return MaterialPurchase::query()
            ->with(['material', 'supplier'])
            ->when($status === 'received', fn (Builder $query): Builder => $query->whereNotNull('receipt_date'))
            ->when($status === 'pending', fn (Builder $query): Builder => $query->whereNull('receipt_date'))
            ->when(
                $materialType !== null,
                fn (Builder $query): Builder => $query->where('material_type', $materialType)
            )
            ->when($supplierId !== null, fn (Builder $query): Builder => $query->where('supplier_id', $supplierId))
            ->orderByDesc('purchase_date')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(MaterialPurchase $purchase): MaterialPurchase
    {
        $purchase->restore();

        return $purchase->load(['material', 'supplier']);
    }

    /**
     * A purchase's material/supplier composition is fixed; only administrative and
     * receipt fields can change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, MaterialPurchase $purchase): MaterialPurchase
    {
        $purchase->fill($attributes);
        $purchase->save();

        if ($purchase->receipt_date !== null) {
            $this->applyPriceFromReceipt($purchase);
        }

        return $purchase->load(['material', 'supplier']);
    }

    private function concreteMaterial(MaterialPurchase $purchase): Clay|Glaze|null
    {
        $material = $purchase->material()->first();

        return $material instanceof Clay || $material instanceof Glaze ? $material : null;
    }

    /**
     * Whether no other received purchase of the same material is more recent than
     * this one (by receipt date, then insertion order as a tie-breaker).
     */
    private function isLatestReceipt(MaterialPurchase $purchase): bool
    {
        $receiptDate = $purchase->receipt_date;

        if ($receiptDate === null) {
            return false;
        }

        return ! MaterialPurchase::query()
            ->where('material_type', $purchase->material_type)
            ->where('material_id', $purchase->material_id)
            ->whereKeyNot($purchase->getKey())
            ->whereNotNull('receipt_date')
            ->where(function (Builder $query) use ($purchase, $receiptDate): void {
                $query->where('receipt_date', '>', $receiptDate->toDateString())
                    ->orWhere(function (Builder $tie) use ($purchase, $receiptDate): void {
                        $tie->where('receipt_date', $receiptDate->toDateString())
                            ->where('id', '>', $purchase->getKey());
                    });
            })
            ->exists();
    }

    private function resolveMaterial(string $materialType, string $ulid): Clay|Glaze
    {
        if ($materialType === 'clay') {
            return Clay::query()->where('ulid', $ulid)->firstOrFail();
        }

        if ($materialType === 'glaze') {
            return Glaze::query()->where('ulid', $ulid)->firstOrFail();
        }

        throw ValidationException::withMessages([
            'material_type' => 'The selected material type is invalid.',
        ]);
    }

    private function resolveSupplierByType(string $materialType, string $ulid): ClaySupplier|GlazeSupplier|null
    {
        if ($materialType === 'clay') {
            return ClaySupplier::query()->where('ulid', $ulid)->first();
        }

        if ($materialType === 'glaze') {
            return GlazeSupplier::query()->where('ulid', $ulid)->first();
        }

        return null;
    }
}
