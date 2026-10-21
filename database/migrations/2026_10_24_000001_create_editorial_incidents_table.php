<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('editorial_incidents')) {
            Schema::create('editorial_incidents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
                $table->string('source_message_id', 255)->index();
                $table->string('original_message_id', 255)->nullable()->index();
                
                // Tipo de incidencia: rectification_data, retraction_withdrawal, event_cancellation, legitimate_update, suspected_duplicate, suspected_impersonation
                $table->string('incident_type', 50)->index();
                
                // Prioridad: low, medium, high, urgent
                $table->string('priority', 20)->default('medium')->index();
                
                // Estado del post al detectar el incidente: draft, scheduled, published, none
                $table->string('post_previous_status', 30);
                
                // Acción automática adoptada: linked_to_draft, halted_scheduled_post, flagged_published_post, retained_ambiguous, ignored_duplicate, blocked_impersonation
                $table->string('action_taken', 50);
                
                // Puntuación y evidencias
                $table->unsignedSmallInteger('correlation_score')->default(0);
                $table->json('correlation_evidence')->nullable();
                
                // Diferencias fácticas detectadas (fechas, recintos, cancelaciones)
                $table->json('detected_diff')->nullable();
                
                // Resolución editorial humana (Fase 6)
                $table->string('resolution_status', 30)->default('pending_review')->index();
                $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                $table->index(['resolution_status', 'priority']);
                $table->index(['created_at', 'incident_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_incidents');
    }
};
