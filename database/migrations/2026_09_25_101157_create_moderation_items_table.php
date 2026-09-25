<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('subject_type', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            
            $table->string('submitter_name', 255)->nullable();
            $table->string('submitter_email', 255)->nullable();
            $table->string('submitter_ip', 45)->nullable();
            
            $table->string('source', 60)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            
            $table->timestamp('notified_at')->nullable();
            $table->integer('notify_attempts')->default(0);
            $table->text('last_notify_error')->nullable();
            
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_items');
    }
};
