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
        Schema::table('theme_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('theme_settings', 'press_feeds_extra')) {
                $table->text('press_feeds_extra')->nullable()->after('post_duplicate_similarity_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (Schema::hasColumn('theme_settings', 'press_feeds_extra')) {
                $table->dropColumn('press_feeds_extra');
            }
        });
    }
};
