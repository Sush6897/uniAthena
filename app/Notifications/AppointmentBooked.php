<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentBooked extends Notification implements ShouldQueue
{
    use Queueable;

    protected $appointment;

    public function __construct($appointment)
    {
        $this->appointment = $appointment;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Appointment Confirmation')
                    ->line('Your appointment has been successfully booked.')
                    ->line('Doctor: ' . $this->appointment->slot->doctor->name)
                    ->line('Date: ' . $this->appointment->slot->date)
                    ->line('Time: ' . $this->appointment->slot->start_time)
                    ->line('Reference: ' . $this->appointment->reference_number)
                    ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'message' => 'Appointment booked successfully with ' . $this->appointment->slot->doctor->name,
        ];
    }
}
