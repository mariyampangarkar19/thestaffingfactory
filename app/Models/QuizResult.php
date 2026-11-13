<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizResult extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['freelancer_profile_id', 'quiz_role', 'score', 'grade', 'passed', 'locale', 'completed_at'];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'score' => 'integer', 'completed_at' => 'datetime'];
    }

    /**
     * Same rule as the Phase 1 WordPress plugin: PASS at 85+, A 95-100, B 90-94, C 85-89.
     *
     * @return array{passed: bool, grade: ?string}
     */
    public static function grade(int $score): array
    {
        $score = max(0, min(100, $score));
        $passed = $score >= (int) config('platform.quiz.pass_mark');
        $grade = null;

        if ($passed) {
            foreach (config('platform.quiz.grades') as $letter => $min) {
                if ($score >= $min) {
                    $grade = $letter;
                    break;
                }
            }
        }

        return ['passed' => $passed, 'grade' => $grade];
    }

    /** @return BelongsTo<FreelancerProfile, $this> */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class);
    }
}
