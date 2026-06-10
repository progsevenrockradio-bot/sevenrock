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
        Schema::table('new_releases', function (Blueprint $table): void {
            if (! Schema::hasColumn('new_releases', 'author_email')) {
                $table->string('author_email')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('new_releases', 'notification_sender')) {
                $table->string('notification_sender')->nullable()->after('author_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_releases', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('new_releases', 'author_email')) {
                $columns[] = 'author_email';
            }
            if (Schema::hasColumn('new_releases', 'notification_sender')) {
                $columns[] = 'notification_sender';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
