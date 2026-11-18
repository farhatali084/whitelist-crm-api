<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('manager_id')->constrained();
            // All times stored in UTC.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('scheduled'); // scheduled | cancelled
            // First slot ever booked; never changes when the meeting is moved (rule 6).
            $table->dateTime('original_starts_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps(); // created_at = when the meeting was booked

            // Overlap checks look up active meetings of one manager / one company by time.
            $table->index(['manager_id', 'status', 'starts_at']);
            $table->index(['company_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
