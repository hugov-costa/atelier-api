<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Bill;
use App\Models\Clay;
use App\Models\ClaySupplier;
use App\Models\Enrollment;
use App\Models\FiringCycle;
use App\Models\Glaze;
use App\Models\GlazeSupplier;
use App\Models\Piece;
use App\Models\PieceCategory;
use App\Models\PieceCharge;
use App\Models\RecurrentClass;
use App\Models\SingleClass;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Database\Seeder;

class AtelierSeeder extends Seeder
{
    public function run(): void
    {
        $claySuppliers = ClaySupplier::factory()->count(2)->create();
        $clays = $claySuppliers->flatMap(
            fn (ClaySupplier $supplier) => Clay::factory()->count(2)->for($supplier)->create()
        );

        $glazeSuppliers = GlazeSupplier::factory()->count(2)->create();
        $glazes = $glazeSuppliers->flatMap(
            fn (GlazeSupplier $supplier) => Glaze::factory()->count(2)->for($supplier)->create()
        );

        $categories = PieceCategory::factory()->count(3)->create();
        $firingCycles = FiringCycle::factory()->count(3)->create();

        Bill::factory()->count(6)->create();

        $students = User::query()->where('role', UserRole::User->value)->get();

        $students->take(5)->each(function (User $student) use ($clays, $glazes, $categories, $firingCycles): void {
            $enrollment = Enrollment::factory()->for($student)->create();
            TuitionFee::factory()->for($enrollment)->create([
                'due_date' => now()->day(10)->toDateString(),
            ]);
            TuitionFee::factory()->for($enrollment)->create([
                'due_date' => now()->addMonthNoOverflow()->day(10)->toDateString(),
            ]);

            $piece = Piece::factory()->student()->for($student)->create([
                'clay_id'           => $clays->random()->id,
                'glaze_id'          => $glazes->random()->id,
                'piece_category_id' => $categories->random()->id,
            ]);

            $cycle = $firingCycles->random();
            $piece->firingCycles()->attach($cycle->id, ['price' => $cycle->price_per_unit]);

            PieceCharge::factory()->for($piece)->for($student)->create([
                'amount'   => $piece->price,
                'due_date' => now()->addMonthNoOverflow()->day(10)->toDateString(),
            ]);
        });

        SingleClass::factory()->count(3)->create()->each(
            fn (SingleClass $class) => $class->users()
                ->attach($students->random(min(2, $students->count()))->pluck('id')->all())
        );

        RecurrentClass::factory()->count(3)->create()->each(
            fn (RecurrentClass $class) => $class->users()
                ->attach($students->random(min(2, $students->count()))->pluck('id')->all())
        );
    }
}
