<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\FiringCycle;
use App\Models\PieceCharge;
use App\Models\Setting;
use App\Models\TuitionFee;
use App\Models\User;
use App\Notifications\BillingStatementNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds and dispatches the consolidated cycle statement (tuition + itemized
 * student pieces) as a PDF-carrying notification. All money stays in integer
 * cents; formatting happens in the Blade view.
 *
 * @phpstan-type MaterialLine array{label: string, quantity: float, unit: string, unit_price: int, cost: int}
 * @phpstan-type FiringLine array{label: string, price: int}
 * @phpstan-type PieceLine array{name: string, materials: list<MaterialLine>, firing: list<FiringLine>, total: int}
 * @phpstan-type Statement array{
 *     student_name: string,
 *     enrollment_number: string|null,
 *     reference_month: string,
 *     due_date: string,
 *     tuition_amount: int|null,
 *     pieces: list<PieceLine>,
 *     total: int,
 *     logo_data_uri: string|null
 * }
 */
class StatementService
{
    /**
     * Portuguese month names indexed 1-12 (avoids depending on an installed locale).
     *
     * @var array<int, string>
     */
    private const MONTHS = [
        1  => 'Janeiro',
        2  => 'Fevereiro',
        3  => 'Março',
        4  => 'Abril',
        5  => 'Maio',
        6  => 'Junho',
        7  => 'Julho',
        8  => 'Agosto',
        9  => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    /**
     * Memoized logo data URI: the same image is embedded in every statement of a run,
     * so it is read and encoded once per service instance rather than once per student.
     */
    private ?string $logoDataUri = null;

    private bool $logoResolved = false;

    /**
     * Build the statement for one student and one cycle due date, or null when
     * there is nothing to bill (no unpaid tuition and no unpaid piece charges).
     *
     * @return Statement|null
     */
    public function buildForUserCycle(string $dueDate, User $user): ?array
    {
        $tuitionAmount = $this->tuitionAmountFor($dueDate, $user);
        $pieces = $this->pieceLinesFor($dueDate, $user);

        if ($tuitionAmount === null && $pieces === []) {
            return null;
        }

        $total = ($tuitionAmount ?? 0) + array_sum(array_map(
            static fn (array $piece): int => $piece['total'],
            $pieces,
        ));

        $enrollmentNumber = Enrollment::query()->where('user_id', $user->id)->value('ulid');
        $enrollmentNumber = is_string($enrollmentNumber) ? $enrollmentNumber : null;

        return [
            'student_name'      => $user->name,
            'enrollment_number' => $enrollmentNumber,
            'reference_month'   => $this->referenceMonth($dueDate),
            'due_date'          => $dueDate,
            'tuition_amount'    => $tuitionAmount,
            'pieces'            => $pieces,
            'total'             => $total,
            'logo_data_uri'     => $this->logoDataUri(),
        ];
    }

    /**
     * Notify every student who has anything to bill on the given cycle due date:
     * an unpaid tuition fee (amount > 0) or an unpaid piece charge. Inactive
     * students with accelerated charges are intentionally included.
     *
     * @param  'generated'|'upcoming'|'overdue'  $context
     */
    public function sendForCycle(string $context, Carbon $dueDate): void
    {
        $date = $dueDate->toDateString();

        $tuitionUserIds = TuitionFee::query()
            ->whereNull('paid_at')
            ->where('amount', '>', 0)
            ->where('due_date', $date)
            ->join('enrollments', 'enrollments.id', '=', 'tuition_fees.enrollment_id')
            ->pluck('enrollments.user_id');

        $chargeUserIds = PieceCharge::query()
            ->whereNull('paid_at')
            ->where('due_date', $date)
            ->pluck('user_id');

        $userIds = $tuitionUserIds->merge($chargeUserIds)->unique()->values();

        User::query()
            ->whereIn('id', $userIds)
            ->chunkById(200, function (Collection $users) use ($date, $context): void {
                foreach ($users as $user) {
                    try {
                        $this->sendForUserCycle($context, $date, $user);
                    } catch (Throwable $e) {
                        Log::error('Failed to send billing statement', [
                            'user_id'   => $user->id,
                            'due_date'  => $date,
                            'exception' => $e,
                        ]);
                    }
                }
            });
    }

    /**
     * Build then, if there is anything to bill, notify the student. Idempotent:
     * a statement already delivered for this student, cycle and context is not
     * sent again, so re-running the generation command or a reminder cron cannot
     * double-email.
     *
     * @param  'generated'|'upcoming'|'overdue'  $context
     */
    public function sendForUserCycle(string $context, string $dueDate, User $user): void
    {
        $statement = $this->buildForUserCycle($dueDate, $user);

        if ($statement === null) {
            return;
        }

        if ($this->alreadyNotified($context, $dueDate, $user)) {
            return;
        }

        $user->notify(new BillingStatementNotification($statement, $context));
    }

    /**
     * Whether a statement for this cycle and context was already delivered to the
     * student (recorded on the database channel).
     */
    private function alreadyNotified(string $context, string $dueDate, User $user): bool
    {
        return $user->notifications()
            ->where('type', BillingStatementNotification::class)
            ->where('data->context', $context)
            ->where('data->due_date', $dueDate)
            ->exists();
    }

    /**
     * The atelier logo as a base64 data URI for embedding in the PDF, or null.
     */
    private function logoDataUri(): ?string
    {
        if ($this->logoResolved) {
            return $this->logoDataUri;
        }

        $this->logoResolved = true;
        $this->logoDataUri = $this->resolveLogoDataUri();

        return $this->logoDataUri;
    }

    /**
     * Itemized piece lines for the student's unpaid charges on the cycle.
     *
     * @return list<PieceLine>
     */
    private function pieceLinesFor(string $dueDate, User $user): array
    {
        /** @var Collection<int, PieceCharge> $charges */
        $charges = PieceCharge::query()
            ->where('user_id', $user->id)
            ->whereNull('paid_at')
            ->where('due_date', $dueDate)
            ->with(['piece.clay', 'piece.glaze', 'piece.firingCycles'])
            ->get();

        $lines = [];

        foreach ($charges as $charge) {
            $piece = $charge->piece;

            $materials = [];

            $materials[] = [
                'label'      => $piece->clay->name,
                'quantity'   => $piece->clay_amount,
                'unit'       => 'kg',
                'unit_price' => $piece->clay_unit_price,
                'cost'       => (int) round($piece->clay_amount * $piece->clay_unit_price),
            ];

            if ($piece->glaze !== null) {
                $glazeAmount = $piece->glaze_amount ?? 0.0;
                $glazeUnitPrice = $piece->glaze_unit_price ?? 0;

                $materials[] = [
                    'label'      => $piece->glaze->name,
                    'quantity'   => $glazeAmount,
                    'unit'       => 'L',
                    'unit_price' => $glazeUnitPrice,
                    'cost'       => (int) round($glazeAmount * $glazeUnitPrice),
                ];
            }

            $firing = [];

            foreach ($piece->firingCycles as $cycle) {
                $firing[] = [
                    'label' => $cycle->name,
                    'price' => $this->pivotPrice($cycle),
                ];
            }

            $lines[] = [
                'name'      => $piece->name,
                'materials' => $materials,
                'firing'    => $firing,
                'total'     => $charge->amount,
            ];
        }

        return $lines;
    }

    /**
     * The firing price snapshotted on the piece's pivot for a cycle, in cents.
     */
    private function pivotPrice(FiringCycle $cycle): int
    {
        $pivot = $cycle->getAttribute('pivot');

        if (! $pivot instanceof Pivot) {
            return 0;
        }

        $price = $pivot->getAttribute('price');

        return is_numeric($price) ? (int) $price : 0;
    }

    /**
     * "Março de 2026" from a due date string.
     */
    private function referenceMonth(string $dueDate): string
    {
        $date = Carbon::parse($dueDate);

        return self::MONTHS[$date->month].' de '.$date->year;
    }

    private function resolveLogoDataUri(): ?string
    {
        $path = Setting::current()->logo_path;

        if ($path === null) {
            return null;
        }

        try {
            $bytes = Storage::disk(LogoService::DISK)->get($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * The unpaid tuition amount owed by this student on the cycle, or null.
     */
    private function tuitionAmountFor(string $dueDate, User $user): ?int
    {
        $fee = TuitionFee::query()
            ->whereNull('paid_at')
            ->where('amount', '>', 0)
            ->where('due_date', $dueDate)
            ->whereHas('enrollment', fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->first();

        return $fee?->amount;
    }
}
