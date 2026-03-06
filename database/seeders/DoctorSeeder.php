<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = \App\Models\Doctor::factory(300)->create();
        $slots = [];
        $now = now();

        foreach ($doctors as $doctor) {
            $generatedSlots = [];
            for ($i = 0; $i < 20; $i++) {
                $date = $now->copy()->addDays(rand(0, 30))->format('Y-m-d');
                $startHour = rand(9, 20);
                
                $slotKey = "{$date}_{$startHour}";
                if (in_array($slotKey, $generatedSlots)) {
                    continue; 
                }
                $generatedSlots[] = $slotKey;

                $startTime = sprintf('%02d:00:00', $startHour);
                $endTime = sprintf('%02d:30:00', $startHour);
                
                $slots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration' => 30,
                    'is_booked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($slots, 500) as $chunk) {
            \App\Models\Slot::insert($chunk);
        }
    }
}
