<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Slot;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Support;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class AppointmentController extends Controller
{
    
    public function index()
    {
        $appointments = Appointment::with(['slot.doctor','patient'])
            ->where('patient_id', auth('patient')->id())
            ->latest()
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No records found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $appointments
        ]);
    }

    
    public function doctorAppointments()
    {
        $appointments = Appointment::with(['patient', 'slot.doctor'])
            ->whereHas('slot', function ($query) {
                $query->where('doctor_id', auth('doctor')->id());
            })
            ->latest()
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No records found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $appointments
        ]);
    }

    
    public function show($id)
    {
        $patientId = auth('patient')->id();
        $doctorId = auth('doctor')->id();

        $appointment = Appointment::with(['slot.doctor', 'patient'])
            ->where(function ($query) use ($patientId, $doctorId) {

                if ($patientId) {
                    $query->where('patient_id', $patientId);
                }

                if ($doctorId) {
                    $query->orWhereHas('slot', function ($q) use ($doctorId) {
                        $q->where('doctor_id', $doctorId);
                    });
                }

            })
            ->where('id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'No record found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $appointment
        ]);
    }

    
    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'slot_id' => 'required|exists:slots,id',
            'reason_for_visit' => 'required|string|max:255',
            'symptoms' => 'required|string|max:255',
            'appointment_type' => 'required|in:in-person,follow-up',
        ]);

        if ($validator->fails() || $request->patient_id != auth('patient')->id()) {
            return response()->json(['errors' => $validator->errors()->isEmpty() ? ['patient_id' => ['Unauthorized action']] : $validator->errors()], 422);
        }

        $patientId = $request->patient_id;
        $slotId = $request->slot_id;
        $reason = $request->reason_for_visit;
        $symptoms = $request->symptoms;
        $type = $request->appointment_type ?? 'in-person';

        try {
            return DB::transaction(function () use ($patientId, $slotId, $reason, $symptoms, $type) {
                $slot = Slot::where('id', $slotId)
                    ->lockForUpdate()
                    ->first();

                if (!$slot || $slot->is_booked) {
                    return response()->json(['message' => 'Slot is already booked or unavailable'], 409);
                }

                if (strtotime($slot->date . ' ' . $slot->start_time) < time()) {
                    return response()->json(['message' => 'Cannot book a past time slot'], 422);
                }

                $referenceNumber = 'APP-' . strtoupper(Str::random(10));

                $appointment = Appointment::create([
                    'patient_id' => $patientId,
                    'slot_id' => $slotId,
                    'reference_number' => $referenceNumber,
                    'status' => 'booked',
                    'reason_for_visit' => $reason,
                    'symptoms' => $symptoms,
                    'appointment_type' => $type,
                ]);

                $slot->update(['is_booked' => true]);

                Cache::forget("doctor_{$slot->doctor_id}_slots_{$slot->date}");

                Notification::create([
                    'patient_id' => $patientId,
                    'message' => "Your appointment with Dr. {$slot->doctor->name} on {$slot->date} at {$slot->start_time} is confirmed.",
                    'type' => 'booking_confirmation',
                ]);

                \App\Jobs\SendAppointmentNotification::dispatch($appointment);

                $appointment->load(['slot.doctor', 'patient']);

                return response()->json([
                    'message' => 'Appointment booked successfully',
                    'appointment' => $appointment
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Booking failed', 'error' => $e->getMessage()], 500);
        }
    }


    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($id, $request) {
                $appointment = Appointment::with('slot')->where('id', $id)
                    ->where(function($q) {
                        $q->where('patient_id', auth('patient')->id())
                          ->orWhereHas('slot', function($sq) {
                              $sq->where('doctor_id', auth('doctor')->id());
                          });
                    })->first();

                if (!$appointment) {
                    return response()->json(['message' => 'No record found'], 404);
                }

                if ($appointment->status === 'cancelled') {
                    return response()->json(['message' => 'Appointment is already cancelled'], 409);
                }

                if ($appointment->status === 'completed') {
                    return response()->json(['message' => 'Cannot cancel a completed appointment'], 422);
                }

                $appointment->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $request->reason,
                ]);

                $appointment->slot->update(['is_booked' => false]);

                Cache::forget("doctor_{$appointment->slot->doctor_id}_slots_{$appointment->slot->date}");

                Notification::create([
                    'patient_id' => $appointment->patient_id,
                    'message' => "Your appointment on {$appointment->slot->date} has been cancelled.",
                    'type' => 'cancellation',
                ]);

                $appointment->load(['slot.doctor', 'patient']);

                return response()->json([
                    'message' => 'Appointment cancelled successfully',
                    'appointment' => $appointment
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cancellation failed', 'error' => $e->getMessage()], 500);
        }
    }

  
    public function reschedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_slot_id' => 'required|exists:slots,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $newSlotId = $request->new_slot_id;

        try {
            return DB::transaction(function () use ($id, $newSlotId) {
                $appointment = Appointment::with('slot')->where('id', $id)
                    ->where('patient_id', auth('patient')->id())->first();

                if (!$appointment) {
                    return response()->json(['message' => 'No record found'], 404);
                }

                $oldSlot = $appointment->slot;

                $userId = auth('patient')->id();
                $isPatient = $appointment->patient_id === $userId;
                $isDoctor = $oldSlot->doctor_id === $userId;

                if (!$isPatient && !$isDoctor) {
                    return response()->json(['message' => 'Unauthorized to reschedule this appointment'], 403);
                }

                if ($appointment->status === 'completed') {
                    return response()->json(['message' => 'Cannot reschedule a completed appointment'], 422);
                }

                $originalStartTime = strtotime($oldSlot->date . ' ' . $oldSlot->start_time);
                if ($originalStartTime - time() < (24 * 3600)) {
                    return response()->json(['message' => 'Appointments can only be rescheduled at least 24 hours in advance'], 422);
                }

                $newSlot = Slot::where('id', $newSlotId)->lockForUpdate()->first();

                if (!$newSlot || $newSlot->is_booked) {
                    return response()->json(['message' => 'New slot is unavailable'], 409);
                }

                if ($newSlot->doctor_id !== $oldSlot->doctor_id) {
                    return response()->json(['message' => 'Cannot reschedule to a different doctor'], 422);
                }

                if (strtotime($newSlot->date . ' ' . $newSlot->start_time) < time()) {
                    return response()->json(['message' => 'Cannot reschedule to a past time slot'], 422);
                }

                $oldSlot->update(['is_booked' => false]);
                Cache::forget("doctor_{$oldSlot->doctor_id}_slots_{$oldSlot->date}");

                $appointment->update([
                    'slot_id' => $newSlotId,
                    'status' => 'rescheduled',
                ]);

                $newSlot->update(['is_booked' => true]);
                Cache::forget("doctor_{$newSlot->doctor_id}_slots_{$newSlot->date}");

                Notification::create([
                    'patient_id' => $appointment->patient_id,
                    'message' => "Your appointment has been rescheduled to {$newSlot->date} at {$newSlot->start_time}.",
                    'type' => 'reschedule',
                ]);

                $appointment->load(['slot.doctor', 'patient']);

                return response()->json([
                    'message' => 'Appointment rescheduled successfully',
                    'appointment' => $appointment
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Rescheduling failed', 'error' => $e->getMessage()], 500);
        }
    }

   
    public function complete($id)
    {
        $appointment = Appointment::with('slot')
            ->whereHas('slot', function ($query) {
                $query->where('doctor_id', auth('doctor')->id());
            })
            ->find($id);

        // dd($appointment);

        if (!$appointment) {
            return response()->json(['message' => 'No record found'], 404);
        }

        if ($appointment->status !== 'booked' && $appointment->status !== 'rescheduled') {
            return response()->json(['message' => 'Only booked or rescheduled appointments can be completed'], 422);
        }

        $appointment->update(['status' => 'completed']);
        $appointment->load(['slot.doctor', 'patient']);

        return response()->json([
            'message' => 'Appointment marked as completed',
            'appointment' => $appointment
        ]);
    }
}
