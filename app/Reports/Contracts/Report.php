<?php

namespace App\Reports\Contracts;

use Carbon\CarbonInterface;

/**
 * One exportable report. To add a report: write a class with this interface and list it in config/platform.php
 * under "reports". The Reports page, the Excel export and the print view pick it up without other changes.
 */
interface Report
{
    public function title(): string;

    public function description(): string;

    /** Permission the signed-in person needs. */
    public function permission(): string;

    /** @return list<string> column headings */
    public function headings(): array;

    /**
     * @return iterable<list<string|int|float|null>> one list per row, in the order of headings()
     */
    public function rows(CarbonInterface $from, CarbonInterface $to): iterable;
}
