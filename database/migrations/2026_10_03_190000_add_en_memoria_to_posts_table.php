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
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'en_memoria')) {
                $table->boolean('en_memoria')->default(false)->after('is_published');
            }
            if (! Schema::hasColumn('posts', 'en_memoria_nombre')) {
                $table->string('en_memoria_nombre')->nullable()->after('en_memoria');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'en_memoria_nombre')) {
                $table->dropColumn('en_memoria_nombre');
            }
            if (Schema::hasColumn('posts', 'en_memoria')) {
                $table->dropColumn('en_memoria');
            }
        });
    }
};
