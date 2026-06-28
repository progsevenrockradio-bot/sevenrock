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
        Schema::table('missing_persons', function (Blueprint $table) {
            $table->string('hospital_admitted_to')->nullable()->after('cedula');
            $table->date('date_update')->nullable()->after('hospital_admitted_to');
            $table->string('service_provided')->nullable()->after('date_update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missing_persons', function (Blueprint $table) {
            $table->dropColumn(['hospital_admitted_to', 'date_update', 'service_provided']);
        });
    }
};
