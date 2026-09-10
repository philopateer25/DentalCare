<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Operatory;
use App\Models\Practice;
use App\Services\CurrencyHelper;
use App\Services\LanguageHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OnboardingController extends Controller
{
    /**
     * Display the clinic onboarding wizard.
     */
    public function show(Request $request): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $practice = $user->practice;

        if (!$practice) {
            // Auto-create initial practice if user has no practice assigned during signup flow
            $practice = Practice::create([
                'name' => 'New Dental Clinic',
                'currency' => 'EGP',
                'locale' => 'en',
                'timezone' => 'Africa/Cairo',
                'is_active' => true,
                'onboarding_step' => 1,
            ]);
            $user->update(['practice_id' => $practice->id]);
        }

        if ($practice->isOnboarded()) {
            return redirect()->route('dashboard');
        }

        $primaryBranch = $practice->branches()->first();
        $operatories = $primaryBranch ? $primaryBranch->operatories : collect([]);
        $doctorProfile = $user->doctorProfile;

        return Inertia::render('onboarding', [
            'step' => (int) ($practice->onboarding_step ?? 1),
            'practice' => [
                'id' => $practice->id,
                'name' => $practice->name,
                'tax_id' => $practice->tax_id,
                'currency' => $practice->currency ?? 'EGP',
                'locale' => $practice->locale ?? 'en',
                'timezone' => $practice->timezone ?? 'Africa/Cairo',
            ],
            'branch' => $primaryBranch ? [
                'id' => $primaryBranch->id,
                'name' => $primaryBranch->name,
                'code' => $primaryBranch->code,
                'address' => $primaryBranch->address,
                'phone' => $primaryBranch->phone,
            ] : null,
            'operatories' => $operatories->pluck('name')->toArray(),
            'doctorProfile' => $doctorProfile ? [
                'specialty' => $doctorProfile->specialty,
                'license_number' => $doctorProfile->license_number,
                'default_commission_percentage' => $doctorProfile->default_commission_percentage,
            ] : null,
            'currencies' => CurrencyHelper::getOptions(),
            'locales' => LanguageHelper::getOptions(),
        ]);
    }

    /**
     * Process and save specific onboarding step data.
     */
    public function saveStep(Request $request): JsonResponse
    {
        $user = $request->user();
        $practice = $user->practice;

        if (!$practice) {
            return response()->json(['message' => 'No practice associated with current user.'], 400);
        }

        $step = (int) $request->input('step', 1);

        switch ($step) {
            case 1:
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'tax_id' => 'nullable|string|max:255',
                ]);

                $practice->update([
                    'name' => $validated['name'],
                    'tax_id' => $validated['tax_id'] ?? null,
                    'onboarding_step' => max($practice->onboarding_step, 2),
                ]);
                break;

            case 2:
                $validated = $request->validate([
                    'branch_name' => 'required|string|max:255',
                    'code' => 'nullable|string|max:50',
                    'address' => 'nullable|string|max:500',
                    'phone' => 'nullable|string|max:50',
                ]);

                $branch = Branch::firstOrNew(['practice_id' => $practice->id]);
                $branch->fill([
                    'name' => $validated['branch_name'],
                    'code' => $validated['code'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'is_active' => true,
                ]);
                $branch->save();

                if (!$user->branch_id) {
                    $user->update(['branch_id' => $branch->id]);
                }

                $practice->update(['onboarding_step' => max($practice->onboarding_step, 3)]);
                break;

            case 3:
                $validated = $request->validate([
                    'operatories' => 'required|array|min:1',
                    'operatories.*' => 'required|string|max:255',
                ]);

                $branch = Branch::firstOrCreate(
                    ['practice_id' => $practice->id],
                    ['name' => $practice->name . ' Main Branch', 'is_active' => true]
                );

                foreach ($validated['operatories'] as $name) {
                    Operatory::firstOrCreate([
                        'branch_id' => $branch->id,
                        'name' => trim($name),
                    ], [
                        'is_active' => true,
                    ]);
                }

                $practice->update(['onboarding_step' => max($practice->onboarding_step, 4)]);
                break;

            case 4:
                $validated = $request->validate([
                    'specialty' => 'required|string|max:255',
                    'license_number' => 'nullable|string|max:255',
                    'default_commission_percentage' => 'required|numeric|min:0|max:100',
                ]);

                DoctorProfile::updateOrCreate([
                    'user_id' => $user->id,
                    'practice_id' => $practice->id,
                ], [
                    'specialty' => $validated['specialty'],
                    'license_number' => $validated['license_number'] ?? null,
                    'default_commission_percentage' => $validated['default_commission_percentage'],
                ]);

                $practice->update(['onboarding_step' => max($practice->onboarding_step, 5)]);
                break;

            case 5:
                $validated = $request->validate([
                    'currency' => 'required|string|max:3',
                    'locale' => 'required|string|in:en,ar,fr',
                    'timezone' => 'required|string|max:255',
                ]);

                $practice->update([
                    'currency' => $validated['currency'],
                    'locale' => $validated['locale'],
                    'timezone' => $validated['timezone'],
                    'onboarding_step' => max($practice->onboarding_step, 6),
                ]);

                $user->update(['locale' => $validated['locale']]);
                break;

            default:
                return response()->json(['message' => 'Invalid onboarding step.'], 422);
        }

        return response()->json([
            'message' => "Step {$step} saved successfully.",
            'next_step' => (int)$practice->fresh()->onboarding_step,
        ]);
    }

    /**
     * Finalize and complete clinic onboarding.
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $practice = $user->practice;

        if (!$practice) {
            return response()->json(['message' => 'No practice associated with current user.'], 400);
        }

        // Ensure minimum requirements are created before marking as complete
        $branch = Branch::firstOrCreate(
            ['practice_id' => $practice->id],
            ['name' => $practice->name . ' Main Branch', 'is_active' => true]
        );

        Operatory::firstOrCreate(
            ['branch_id' => $branch->id],
            ['name' => 'Operatory 1', 'is_active' => true]
        );

        $practice->update([
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Clinic onboarding completed successfully!',
            'redirect' => route('dashboard'),
        ]);
    }
}
