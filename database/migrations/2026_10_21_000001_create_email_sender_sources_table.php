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
        if (! Schema::hasTable('email_sender_sources')) {
            Schema::create('email_sender_sources', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('slug', 160)->unique();
                $table->string('organization_type', 50)->default('record_label');
                $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
                $table->json('authorized_emails');
                $table->json('verified_domains');
                $table->string('status', 30)->default('pending'); // authorized, pending, blocked, suspended
                $table->boolean('auto_publish_allowed')->default(false);
                $table->timestamp('authorized_at')->nullable();
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->integer('incidents_count')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('organization_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_sender_sources');
    }
};
