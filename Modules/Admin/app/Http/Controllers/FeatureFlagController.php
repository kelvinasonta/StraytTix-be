<?php

namespace Modules\Admin\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class FeatureFlagController extends Controller
{
    /**
     * Toggle a feature flag.
     */
    public function toggle(Request $request, string $feature)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        if ($request->boolean('is_active')) {
            Feature::for('global')->activate($feature);
        } else {
            Feature::for('global')->deactivate($feature);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Feature flag '{$feature}' has been ".($request->boolean('is_active') ? 'activated' : 'deactivated').' for everyone.',
            'feature' => $feature,
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
