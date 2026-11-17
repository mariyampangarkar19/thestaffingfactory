<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Reports\Contracts\Report;
use App\Reports\ReportRegistry;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports & Exports (quote section 1): Excel download, or a print page the browser saves as PDF.
 * Signed-in agency staff only; each report names the permission it needs; every export is logged.
 */
final class ReportExportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __invoke(Request $request, string $report, ReportRegistry $reports, TenantContext $tenant, AuditLogger $audit): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canAccessPanel(filament()->getPanel('crm')), 403);

        $definition = $reports->find($report);
        abort_if($definition === null, 404);
        abort_unless($user->can('reports.view') && $user->can($definition->permission()), 403);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'format' => ['required', 'in:xlsx,print'],
        ]);

        $timezone = $tenant->require()->timezone ?: 'UTC';
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], $timezone);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], $timezone)->endOfDay();
        abort_if($from->diffInDays($to) > self::MAX_RANGE_DAYS, 422, __('Choose a period of one year or less.'));

        $audit->log('report.exported', null, ['report' => $report, 'format' => $data['format'], 'from' => $data['from'], 'to' => $data['to']]);

        $rows = $definition->rows($from->utc(), $to->utc());

        if ($data['format'] === 'print') {
            return response()->view('reports.print', [
                'report' => $definition,
                'rows' => $rows,
                'from' => $from,
                'to' => $to,
                'agency' => $tenant->require()->name,
            ])->header('Cache-Control', 'private, no-store');
        }

        return $this->xlsx($definition, $rows, Str::slug($definition->title()).'-'.$data['from'].'-'.$data['to'].'.xlsx');
    }

    /** @param iterable<list<string|int|float|null>> $rows */
    private function xlsx(Report $report, iterable $rows, string $filename): Response
    {
        return response()->streamDownload(function () use ($report, $rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($report->headings(), (new Style)->setFontBold()));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(self::safeCell(...), $row)));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Text typed by a member of the public (a name on the website form) must never run as a spreadsheet formula.
     */
    private static function safeCell(string|int|float|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
