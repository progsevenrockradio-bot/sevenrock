<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_storage_cleanups', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 64);
            $table->string('storage_key', 512);
            $table->string('operation_id', 64)->nullable()->index();
            $table->string('reason', 128)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempts']);
            $table->index(['disk', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_storage_cleanups');
    }
};

