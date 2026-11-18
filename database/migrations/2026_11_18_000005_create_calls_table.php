<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('manager_id')->constrained();
            $table->string('result', 20); // no_answer | refused | meeting_set
            // Required when result = meeting_set (rule 5); one call per meeting.
            $table->foreignId('meeting_id')->nullable()->unique()->constrained();
            $table->text('comment')->nullable();
            $table->dateTime('called_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
