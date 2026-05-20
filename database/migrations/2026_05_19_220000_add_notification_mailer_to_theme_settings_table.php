<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->string('notification_email')->nullable()->after('contact_email');
            $table->string('notification_copy_email')->nullable()->after('notification_email');
            $table->string('notification_from_email')->nullable()->after('notification_copy_email');
            $table->string('notification_reply_to_email')->nullable()->after('notification_from_email');
            $table->string('notification_mailer', 50)->nullable()->after('notification_reply_to_email');
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->dropColumn('notification_mailer');
        });
    }
};
