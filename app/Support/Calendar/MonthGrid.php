<?php

namespace App\Support\Calendar;

use Carbon\CarbonImmutable;

/**
 * A month laid out in weeks (Monday first) with the events of each day. Used by the agency calendar and
 * the freelancer's "My calendar", so both look and behave the same.
 */
final class MonthGrid
{
    public readonly CarbonImmutable $month;

    /** @var array<string, list<array{label: string, time: string, url: ?string, class: string, title: string}>> */
    private array $events = [];

    public function __construct(?string $month, public readonly string $timezone)
    {
        $parsed = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? CarbonImmutable::createFromFormat('!Y-m', $month, $timezone)
            : null;

        $this->month = ($parsed ?: CarbonImmutable::now($timezone))->startOfMonth();
    }

    public function key(): string
    {
        return $this->month->format('Y-m');
    }

    public function previous(): string
    {
        return $this->month->subMonth()->format('Y-m');
    }

    public function next(): string
    {
        return $this->month->addMonth()->format('Y-m');
    }

    public function title(): string
    {
        return ucfirst($this->month->locale(app()->getLocale())->isoFormat('MMMM YYYY'));
    }

    /** First and last instant shown, in UTC, for the database query. */
    public function rangeUtc(): array
    {
        return [$this->firstDay()->utc(), $this->lastDay()->endOfDay()->utc()];
    }

    public function add(\DateTimeInterface $startsAt, \DateTimeInterface $endsAt, string $label, ?string $url = null, string $class = '', ?string $title = null): void
    {
        $start = CarbonImmutable::instance($startsAt)->setTimezone($this->timezone);
        $time = $start->format('H:i').'–'.CarbonImmutable::instance($endsAt)->setTimezone($this->timezone)->format('H:i');

        $this->events[$start->toDateString()][] = [
            'label' => $label, 'time' => $time, 'url' => $url, 'class' => $class, 'title' => $title ?? $label,
        ];
    }

    /** @return list<string> */
    public function weekdays(): array
    {
        $monday = CarbonImmutable::parse('2024-01-01')->locale(app()->getLocale());

        return array_map(fn (int $i) => $monday->addDays($i)->isoFormat('ddd'), range(0, 6));
    }

    /** @return list<array{date: CarbonImmutable, label: string, inMonth: bool, isToday: bool, events: list<array<string, mixed>>}> */
    public function days(): array
    {
        $today = CarbonImmutable::now($this->timezone)->toDateString();
        $days = [];

        for ($day = $this->firstDay(); $day->lte($this->lastDay()); $day = $day->addDay()) {
            $days[] = [
                'date' => $day,
                'label' => $day->locale(app()->getLocale())->isoFormat('ddd D MMM'),
                'inMonth' => $day->month === $this->month->month,
                'isToday' => $day->toDateString() === $today,
                'events' => $this->events[$day->toDateString()] ?? [],
            ];
        }

        return $days;
    }

    private function firstDay(): CarbonImmutable
    {
        return $this->month->startOfWeek(CarbonImmutable::MONDAY);
    }

    private function lastDay(): CarbonImmutable
    {
        return $this->month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
    }
}
