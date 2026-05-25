<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('facebook_url')->nullable()->after('featured_image_path');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('twitter_url')->nullable()->after('instagram_url');
            $table->string('youtube_url')->nullable()->after('twitter_url');
            $table->string('external_link_url')->nullable()->after('youtube_url');
            $table->string('external_link_label')->nullable()->after('external_link_url');
            $table->string('source_name')->nullable()->after('external_link_label');
            $table->string('source_url')->nullable()->after('source_name');
            $table->string('meta_title', 120)->nullable()->after('source_url');
            $table->text('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn([
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'youtube_url',
                'external_link_url',
                'external_link_label',
                'source_name',
                'source_url',
                'meta_title',
                'meta_description',
            ]);
        });
    }
};
