<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CentralLicenseMockController extends Controller
{
    /**
     * Simulates the Central API endpoint for verifying/enrolling a license.
     */
    public function enroll(Request $request)
    {
        $code = $request->input('code');

        // Hardcode a valid code for testing purposes
        if ($code === 'CLINIC-XYZ-123') {
            return response()->json([
                'status' => 'active',
                'clinic_id' => 1,
                'hardware_id' => $request->input('hardware_id'),
                'message' => 'Enrollment successful'
            ]);
        }

        return response()->json([
            'status' => 'inactive',
            'error' => 'Invalid enrollment code'
        ], 400);
    }

    /**
     * Simulates the Central API endpoint for receiving heartbeats and returning state.
     */
    public function heartbeat(Request $request)
    {
        $clinicId = $request->input('clinic_id');
        
        // Mock a toggle mechanism for testing suspension
        // In reality, this would query the Central Database
        $mockState = 'active'; // Change to 'suspended' manually to test suspension

        return response()->json([
            'status' => $mockState,
            'message' => 'Heartbeat received'
        ]);
    }
}
