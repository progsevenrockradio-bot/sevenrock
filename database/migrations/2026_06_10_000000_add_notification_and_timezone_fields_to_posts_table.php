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
        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'status')) {
                $table->string('status')->default('published')->after('is_published');
            }
            if (! Schema::hasColumn('posts', 'author_email')) {
                $table->string('author_email')->nullable()->after('status');
            }
            if (! Schema::hasColumn('posts', 'notification_sender')) {
                $table->string('notification_sender')->nullable()->after('author_email');
            }
            if (! Schema::hasColumn('posts', 'timezone')) {
                $table->string('timezone')->default('Europe/Madrid')->after('notification_sender');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('posts', 'status')) {
                $columns[] = 'status';
            }
            if (Schema::hasColumn('posts', 'author_email')) {
                $columns[] = 'author_email';
            }
            if (Schema::hasColumn('posts', 'notification_sender')) {
                $columns[] = 'notification_sender';
            }
            if (Schema::hasColumn('posts', 'timezone')) {
                $columns[] = 'timezone';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
