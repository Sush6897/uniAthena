## Tech Stack

- **Framework**: Laravel 11 (Stateless API)
- **Database**: MySQL 8.0+
- **Security**: Laravel Sanctum
- **Queue/Cache**: Database (Scalable to Redis)

## Installation & Setup

1. **Clone & Install**:
   ```bash
   composer install
   ```

2. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Initialization**:
   This will create the schema and seed 300 doctors with availability.
   ```bash
   php artisan migrate --seed
   ```

4. **Background Process**:
   ```bash
   php artisan queue:work
   ```

5. **Postman Testing**:
   Import `appointment_system_collection.json` into Postman to test all endpoints. Update the `token` variable with the key received after login.

6. **Real Email Testing (Optional)**:
   By default, emails are saved to `storage/logs/laravel.log`. To see real emails:
   - Create a free account at [Mailosaur](https://mailosaur.com).
   - Update `MAIL_MAILER=smtp` in `.env`.
   - Paste your **Server ID** and **API Key** into `.env`.

## Design & Scalability

- **Data Privacy & Security**: A strict privacy policy is enforced. Authenticated users (Patients or Doctors) can only access and manage their own data. Any attempt to access unauthorized records or non-existent data will return a standardized "No record found" response.
- **High-Concurrency Booking**: The system is designed to handle **10,000+ bookings per day**. To prevent race conditions where two patients book the same slot simultaneously:
    - Each booking request opens a Database Transaction.
    - The specific `Slot` row is locked using `FOR UPDATE`.
    - The transaction only commits if the slot is still available, ensuring absolute data integrity.



## API Directory

### Auth
- `POST /api/login/patient`
- `POST /api/login/doctor`
- `POST /api/logout`

### Patient Endpoints (Requires `auth:patient`)
- **Doctor Discovery**:
    - `GET /api/doctors`
    - `GET /api/doctors/{id}/availability?date=YYYY-MM-DD`
- **Appointments**:
    - `GET /api/appointments`
    - `GET /api/appointments/{id}` 
    - `POST /api/appointments/book` 
    - `POST /api/appointments/{id}/reschedule` 
    - `POST /api/appointments/{id}/cancel` 
- **Notifications**:
    - `GET /api/notifications`

### Doctor Endpoints (Requires `auth:doctor`)
- `POST /api/doctors/availability`
- `GET /api/doctors/my-slots`
- `DELETE /api/doctors/slots/{id}`
- `GET /api/doctors/appointments`
- `POST /api/appointments/{id}/complete`


