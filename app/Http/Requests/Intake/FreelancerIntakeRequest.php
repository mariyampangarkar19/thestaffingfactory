<?php

namespace App\Http\Requests\Intake;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload sent by the WordPress plugin when a freelancer signs up or finishes the quiz.
 * The plugin stores the CV itself; files are pulled into the platform in milestone 2.
 */
final class FreelancerIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorised by VerifyIntegrationSignature.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'external_ref' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{6,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{6,32}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'roles' => ['nullable', 'array', 'max:10'],
            'roles.*' => ['string', 'max:60'],
            'languages' => ['nullable', 'array', 'max:5'],
            'languages.*' => ['string', 'in:en,fr'],
            'source' => ['nullable', 'string', 'max:60'],
            'consented_at' => ['required', 'date'],
            'quiz' => ['nullable', 'array'],
            'quiz.role' => ['required_with:quiz', 'string', 'max:60'],
            'quiz.score' => ['required_with:quiz', 'integer', 'between:0,100'],
            'quiz.locale' => ['nullable', 'in:en,fr'],
            'quiz.completed_at' => ['required_with:quiz', 'date'],
        ];
    }
}
