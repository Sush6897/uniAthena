<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    public function index()
    {
        $doctors = Doctor::all();
        if ($doctors->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No record found'], 404);
        }
        return response()->json($doctors);
    }

    public function availability(Request $request, $doctorId)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        
        $cacheKey = "doctor_{$doctorId}_slots_{$date}";

        $slots = Cache::remember($cacheKey, 60, function () use ($doctorId, $date) {
            return Slot::with('doctor')->where('doctor_id', $doctorId)
                ->where('date', $date)
                ->where('is_booked', false)
                ->get();
        });

        if ($slots->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No record found'], 404);
        }

        return response()->json($slots);
    }


    public function storeAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_duration' => 'required|integer|min:15',
        ]);

        if ($validator->fails() || $request->doctor_id != auth('doctor')->id()) {
            return response()->json(['errors' => $validator->errors()->isEmpty() ? ['doctor_id' => ['Unauthorized action']] : $validator->errors()], 422);
        }

        $doctorId = $request->doctor_id;
        $date = $request->date;
        $startTimeRaw = $request->start_time;
        $endTimeRaw = $request->end_time;
        $duration = $request->slot_duration;
        $startTs = strtotime($startTimeRaw);
        $endTs = strtotime($endTimeRaw);
        $diffMinutes = ($endTs - $startTs) / 60;
        if ($diffMinutes < $duration) {
            return response()->json([
                'error' => "The time range ({$diffMinutes} minutes) is shorter than the slot duration ({$duration} minutes). At least one full slot must fit in the range."
            ], 422);
        }

        $startTime = $startTimeRaw . ':00';
        $endTime = $endTimeRaw . ':00';

        $overlapExists = Slot::where('doctor_id', $doctorId)
            ->where('date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($overlapExists) {
            return response()->json([
                'error' => "One or more slots already exist for this doctor between {$startTimeRaw} and {$endTimeRaw} on {$date}."
            ], 422);
        }

        $createdSlots = [];
        $current = strtotime($startTime);
        $end = strtotime($endTime);

        while ($current + ($duration * 60) <= $end) {
            $slotStart = date('H:i:s', $current);
            $slotEnd = date('H:i:s', $current + ($duration * 60));

            $createdSlots[] = Slot::create([
                'doctor_id' => $doctorId,
                'date' => $date,
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
                'duration' => $duration,
                'is_booked' => false,
            ]);

            $current += ($duration * 60);
        }
        $slotsWithDoctor = Slot::with('doctor')
            ->whereIn('id', collect($createdSlots)->pluck('id'))
            ->get();
        Cache::forget("doctor_{$doctorId}_slots_{$date}");

        return response()->json([
            'message' => 'Slots created successfully',
            'slots' => $slotsWithDoctor
        ], 201);
    }

  
    public function mySlots(Request $request)
    {
        $date = $request->query('date');
        
        $query = Slot::with('doctor')->where('doctor_id', auth('doctor')->id())
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc');

        if ($date) {
            $query->where('date', $date);
        }

        $slots = $query->get();
        if ($slots->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No record found'], 404);
        }

        return response()->json($slots);
    }

   
    public function deleteSlot($id)
    {
        $slot = Slot::where('id', $id)
            ->where('doctor_id', auth('doctor')->id())
            ->first();

        if (!$slot) {
            return response()->json(['message' => 'No record found'], 404);
        }

        if ($slot->is_booked) {
            return response()->json(['message' => 'Cannot delete a booked slot. Please cancel the appointment first.'], 422);
        }

        $slot->delete();
        Cache::forget("doctor_{$slot->doctor_id}_slots_{$slot->date}");

        return response()->json(['message' => 'Slot deleted successfully']);
    }

    
   
}
