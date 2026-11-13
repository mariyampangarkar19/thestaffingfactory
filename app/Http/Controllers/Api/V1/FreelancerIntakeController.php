<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpsertFreelancerFromIntake;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intake\FreelancerIntakeRequest;
use Illuminate\Http\JsonResponse;

final class FreelancerIntakeController extends Controller
{
    public function __invoke(FreelancerIntakeRequest $request, UpsertFreelancerFromIntake $action): JsonResponse
    {
        $profile = $action->handle($request->validated());

        return response()->json(['id' => $profile->id], $profile->wasRecentlyCreated ? 201 : 200);
    }
}
