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
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_manual')->default(0)->after('is_featured');
            $table->string('source_type', 30)->nullable()->after('is_manual');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->timestamp('featured_at')->nullable()->after('source_id');
            
            $table->index(['is_manual', 'featured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['is_manual', 'featured_at']);
            $table->dropColumn(['is_manual', 'source_type', 'source_id', 'featured_at']);
        });
    }
};
