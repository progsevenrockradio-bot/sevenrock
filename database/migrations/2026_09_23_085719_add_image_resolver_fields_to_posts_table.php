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
            if (!Schema::hasColumn('posts', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('status');
            }
            if (!Schema::hasColumn('posts', 'source_name')) {
                $table->string('source_name', 255)->nullable()->after('source_url');
            }
            if (!Schema::hasColumn('posts', 'image_source')) {
                $table->string('image_source', 30)->nullable()->after('source_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'source_url')) {
                $table->dropColumn('source_url');
            }
            if (Schema::hasColumn('posts', 'source_name')) {
                $table->dropColumn('source_name');
            }
            if (Schema::hasColumn('posts', 'image_source')) {
                $table->dropColumn('image_source');
            }
        });
    }
};
