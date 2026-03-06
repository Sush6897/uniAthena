<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated patient.
     */
    public function index()
    {
        $notifications = Notification::where('patient_id', auth()->id())
            ->orderBy('id', 'desc')
            ->get();

        if ($notifications->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No record found'
            ], 404);
        }

        return response()->json($notifications);
    }
}
