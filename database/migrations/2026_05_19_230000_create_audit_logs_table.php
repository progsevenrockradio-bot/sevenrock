<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('actor_type', 50)->nullable();
            $table->string('category', 50)->index();
            $table->string('event', 100)->index();
            $table->string('level', 20)->default('info')->index();
            $table->string('summary')->nullable();
            $table->nullableMorphs('subject');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('request_meta')->nullable();
            $table->json('response_meta')->nullable();
            $table->string('method', 10)->nullable()->index();
            $table->string('route_name')->nullable()->index();
            $table->text('url')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
