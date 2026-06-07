<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_program_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radio_program_id')->constrained('radio_programs')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->text('event_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['radio_program_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_program_events');
    }
};
