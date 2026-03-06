<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('reason_for_visit')->nullable()->after('status');
            $table->text('symptoms')->nullable()->after('reason_for_visit');
            $table->enum('appointment_type', ['in-person', 'follow-up'])->default('in-person')->after('symptoms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['reason_for_visit', 'symptoms', 'appointment_type']);
        });
    }
};
