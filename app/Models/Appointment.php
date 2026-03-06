<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 
        'slot_id', 
        'reference_number', 
        'status', 
        'cancellation_reason',
        'reason_for_visit',
        'symptoms',
        'appointment_type'
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
