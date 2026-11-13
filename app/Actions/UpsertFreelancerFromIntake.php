<?php

namespace App\Actions;

use App\Audit\AuditLogger;
use App\Models\FreelancerProfile;
use App\Models\JobRole;
use App\Models\QuizResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates or updates a freelancer from the website. Idempotent on external_ref, so the plugin can safely retry.
 */
final class UpsertFreelancerFromIntake
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): FreelancerProfile
    {
        return DB::transaction(function () use ($data): FreelancerProfile {
            [$first, $last] = $this->splitName($data['full_name']);

            $profile = FreelancerProfile::query()->firstOrNew(['external_ref' => $data['external_ref']]);
            $isNew = ! $profile->exists;

            $profile->fill(array_filter([
                'first_name' => $first,
                'last_name' => $last,
                'email' => isset($data['email']) ? strtolower($data['email']) : null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'city' => $data['city'] ?? null,
                'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
                'languages' => $data['languages'] ?? null,
                'source' => $data['source'] ?? null,
                'consented_at' => $data['consented_at'],
            ], fn ($v) => $v !== null))->save();

            if (! empty($data['roles'])) {
                $ids = collect($data['roles'])->map(function (string $role) {
                    $slug = Str::slug($role);
                    $label = Str::headline($role);

                    return JobRole::query()->firstOrCreate(['slug' => $slug], ['name_en' => $label, 'name_fr' => $label])->id;
                });
                $profile->jobRoles()->syncWithoutDetaching($ids->all());
            }

            if (! empty($data['quiz'])) {
                $graded = QuizResult::grade((int) $data['quiz']['score']);
                $profile->quizResults()->updateOrCreate(
                    ['quiz_role' => $data['quiz']['role'], 'completed_at' => $data['quiz']['completed_at']],
                    ['score' => (int) $data['quiz']['score'], 'locale' => $data['quiz']['locale'] ?? 'fr', ...$graded],
                );
            }

            $this->audit->log(
                $isNew ? 'freelancer.created' : 'freelancer.updated',
                $profile,
                ['via' => 'website', 'fields' => array_keys($profile->getChanges() ?: $profile->getAttributes())],
                'integration',
            );

            return $profile;
        });
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        $first = array_shift($parts) ?? '';

        return [$first, implode(' ', $parts)];
    }
}
