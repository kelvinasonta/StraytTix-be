<?php

namespace Modules\Shared\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Shared\app\Contracts\AppSettingsServiceInterface;
use Modules\Shared\app\DTOs\AppSettingsDTO;
use Modules\Shared\app\Http\Requests\AppSettingsRequest;

class AppSettingsController extends Controller
{
    public function __construct(
        protected AppSettingsServiceInterface $settingsService
    ) {}

    /**
     * Display the current application settings.
     */
    public function index(): JsonResponse
    {
        $settings = $this->settingsService->getAll();

        return ApiResponse::success(
            'App settings retrieved successfully.',
            $settings->toArray()
        );
    }

    /**
     * Store or update application settings.
     */
    public function store(AppSettingsRequest $request): JsonResponse
    {
        $appSettingsDTO = AppSettingsDTO::fromArray($request->validated());

        $updatedSettings = $this->settingsService->updateSettings($appSettingsDTO);

        return ApiResponse::success(
            'App settings updated successfully.',
            $updatedSettings->toArray()
        );
    }
}
