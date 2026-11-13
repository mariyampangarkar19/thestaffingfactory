<?php

namespace Tests\Unit;

use App\Models\QuizResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizGradeTest extends TestCase
{
    /** Same bands as the Phase 1 WordPress plugin. */
    public static function scores(): array
    {
        return [[100, true, 'A'], [95, true, 'A'], [94, true, 'B'], [90, true, 'B'], [89, true, 'C'], [85, true, 'C'], [84, false, null], [0, false, null]];
    }

    #[DataProvider('scores')]
    public function test_grade(int $score, bool $passed, ?string $grade): void
    {
        $this->assertSame(['passed' => $passed, 'grade' => $grade], QuizResult::grade($score));
    }
}
