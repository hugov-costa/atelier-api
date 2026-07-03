<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Report\MonthlyReportRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ReportService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Reports', weight: 70)]
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /**
     * Monthly summary
     *
     * Returns a month's revenue (paid tuition, annual fees, piece charges and commission sales),
     * expenses (bills + material purchases), net, realized commission margin, per-category piece
     * margins and activity counts. Defaults to the current month. Restricted to staff.
     */
    public function monthly(MonthlyReportRequest $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->format('Y'));
        $month = $request->integer('month', (int) now()->format('n'));

        return ApiResponse::item($this->reports->monthly($month, $year));
    }
}
