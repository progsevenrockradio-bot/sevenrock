<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radio_artist_id')->nullable()->constrained('radio_artists')->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('band_contacts');
    }
};
