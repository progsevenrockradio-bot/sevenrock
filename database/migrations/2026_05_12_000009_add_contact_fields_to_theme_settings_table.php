<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->string('contact_form_title')->nullable()->after('home_video_image_path');
            $table->string('contact_info_title')->nullable()->after('contact_form_title');
            $table->text('contact_description')->nullable()->after('contact_info_title');
            $table->text('contact_address')->nullable()->after('contact_description');
            $table->string('contact_email')->nullable()->after('contact_address');
            $table->string('contact_phone_primary')->nullable()->after('contact_email');
            $table->string('contact_phone_secondary')->nullable()->after('contact_phone_primary');
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_form_title',
                'contact_info_title',
                'contact_description',
                'contact_address',
                'contact_email',
                'contact_phone_primary',
                'contact_phone_secondary',
            ]);
        });
    }
};
