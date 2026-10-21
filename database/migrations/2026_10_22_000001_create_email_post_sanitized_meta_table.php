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
        if (! Schema::hasTable('email_post_sanitized_meta')) {
            Schema::create('email_post_sanitized_meta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
                $table->string('source_message_id', 255)->nullable()->index();
                $table->string('sender_email', 255)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->date('musical_release_date')->nullable();
                $table->timestamp('embargo_until')->nullable();
                $table->timestamp('publish_permitted_at')->nullable();
                $table->boolean('is_embargo_active')->default(false);
                $table->text('editorial_notes')->nullable();
                $table->string('security_status', 50)->default('passed'); // passed, held_embargo, held_security, needs_review
                $table->text('incident_reason')->nullable();
                $table->json('redacted_links')->nullable();
                $table->string('superseded_by_message_id', 255)->nullable();
                $table->timestamps();

                $table->index('security_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_post_sanitized_meta');
    }
};
