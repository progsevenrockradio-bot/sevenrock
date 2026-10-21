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
        Schema::table('new_releases', function (Blueprint $table) {
            if (! Schema::hasColumn('new_releases', 'origin')) {
                $table->string('origin', 50)->default('unknown')->after('author_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_releases', function (Blueprint $table) {
            if (Schema::hasColumn('new_releases', 'origin')) {
                $table->dropColumn('origin');
            }
        });
    }
};
