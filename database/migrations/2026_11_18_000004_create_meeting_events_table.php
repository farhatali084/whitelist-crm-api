<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Append-only history of a meeting: created, rescheduled, cancelled (rule 6). */
    public function up(): void
    {
        Schema::create('meeting_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // created | rescheduled | cancelled
            $table->dateTime('old_starts_at')->nullable();
            $table->dateTime('new_starts_at')->nullable();
            $table->unsignedBigInteger('old_manager_id')->nullable();
            $table->unsignedBigInteger('new_manager_id')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_events');
    }
};
