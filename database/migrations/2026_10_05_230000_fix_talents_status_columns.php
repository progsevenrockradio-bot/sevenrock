<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents', function (Blueprint $table): void {
            if (Schema::hasColumn('talents', 'subscription_status')) {
                $table->string('subscription_status', 32)->default('inactive')->change();
            }
            if (Schema::hasColumn('talents', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Volver al enum original, por si se revierte.
        Schema::table('talents', function (Blueprint $table): void {
            if (Schema::hasColumn('talents', 'subscription_status')) {
                $table->enum('subscription_status', ['active', 'inactive', 'cancelled'])
                      ->default('inactive')->change();
            }
        });
    }
};
