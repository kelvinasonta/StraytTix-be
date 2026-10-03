<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Modules\Auth\app\DTOs\PasswordUpdateDTO;
use Modules\Auth\app\DTOs\ProfileUpdateDTO;
use Modules\Auth\app\Http\Requests\UpdatePasswordRequest;
use Modules\Auth\app\Http\Requests\UpdateProfileRequest;
use Modules\Auth\app\Services\ProfileService;
use Modules\Auth\app\Transformers\UserResource;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $profileUpdateDTO = ProfileUpdateDTO::fromArray($request->validated());

        $this->profileService->profileUpdate($user, $profileUpdateDTO);

        return ApiResponse::success(
            'Profile updated successfully.',
            new UserResource($user->fresh())
        );
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();

        $passwordUpdateDTO = PasswordUpdateDTO::fromArray($request->validated());

        $this->profileService->passwordUpdate($user, $passwordUpdateDTO);

        return ApiResponse::success('Password updated successfully.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Soft delete user
        $user->delete();

        return ApiResponse::success('Account deleted successfully.');
    }
}
