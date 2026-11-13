<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\FreelancerProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $status
 */
class FreelancerProfile extends Model
{
    /** @use HasFactory<FreelancerProfileFactory> */
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'whatsapp', 'country_code', 'city', 'address', 'date_of_birth', 'gender',
        'languages', 'experience_summary', 'years_experience', 'country_data', 'status', 'availability_status',
        'source', 'external_ref', 'consented_at',
    ];

    protected $attributes = ['languages' => '[]', 'country_data' => '{}'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'languages' => 'array',
            'country_data' => 'array',
            'consented_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<JobRole, $this> */
    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'freelancer_job_role')->withPivot('years_experience');
    }

    /** @return HasMany<QuizResult, $this> */
    public function quizResults(): HasMany
    {
        return $this->hasMany(QuizResult::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
