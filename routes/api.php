<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;



Route::post('/login/patient', [AuthController::class, 'loginPatient']);
Route::post('/login/doctor', [AuthController::class, 'loginDoctor']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:patient,doctor');
Route::get('/appointments/{id}', [AppointmentController::class, 'show'])->middleware('auth:patient,doctor');




Route::middleware('auth:patient')->group(function () {


    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{id}/availability', [DoctorController::class, 'availability']);

    Route::get('/appointments', [AppointmentController::class, 'index']);

    Route::post('/appointments/book', [AppointmentController::class, 'book']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);

    Route::get('/notifications', [NotificationController::class, 'index']);
});


Route::middleware('auth:doctor')->group(function () {


    Route::post('/doctors/availability', [DoctorController::class, 'storeAvailability']);
    Route::get('/doctors/my-slots', [DoctorController::class, 'mySlots']);
    Route::delete('/doctors/slots/{id}', [DoctorController::class, 'deleteSlot']);

    Route::get('/doctors/appointments', [AppointmentController::class, 'doctorAppointments']);
    Route::post('/appointments/{id}/complete', [AppointmentController::class, 'complete']);
});