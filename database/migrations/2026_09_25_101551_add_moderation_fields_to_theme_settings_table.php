<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            $table->boolean('moderation_enabled')->default(true);
            $table->text('moderation_extra_emails')->nullable();
            $table->integer('moderation_digest_minutes')->default(10);
            
            // Interruptores por tipo
            $table->boolean('moderation_require_submission')->default(true);
            $table->boolean('moderation_require_media')->default(true);
            $table->boolean('moderation_require_album')->default(true);
            $table->boolean('moderation_require_product')->default(true);
            $table->boolean('moderation_require_wall_post')->default(true);
            $table->boolean('moderation_require_comment')->default(true);
            $table->boolean('moderation_require_talent_registration')->default(true);
            $table->boolean('moderation_require_affiliate')->default(true);
            $table->boolean('moderation_require_agency_band')->default(true);
            $table->boolean('moderation_require_contact')->default(true);
            $table->boolean('moderation_require_contract')->default(true);
            $table->boolean('moderation_require_event')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            $table->dropColumn([
                'moderation_enabled',
                'moderation_extra_emails',
                'moderation_digest_minutes',
                'moderation_require_submission',
                'moderation_require_media',
                'moderation_require_album',
                'moderation_require_product',
                'moderation_require_wall_post',
                'moderation_require_comment',
                'moderation_require_talent_registration',
                'moderation_require_affiliate',
                'moderation_require_agency_band',
                'moderation_require_contact',
                'moderation_require_contract',
                'moderation_require_event',
            ]);
        });
    }
};
