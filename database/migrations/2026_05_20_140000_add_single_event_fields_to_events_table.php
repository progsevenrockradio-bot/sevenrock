<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('categories')->nullable()->after('ticket_label');
            $table->string('poster')->nullable()->after('categories');
            $table->string('venue_url')->nullable()->after('poster');
            $table->string('facebook_url')->nullable()->after('venue_url');
            $table->string('embed_url')->nullable()->after('facebook_url');
            $table->string('map_url')->nullable()->after('embed_url');
            $table->json('content')->nullable()->after('map_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'categories',
                'poster',
                'venue_url',
                'facebook_url',
                'embed_url',
                'map_url',
                'content',
            ]);
        });
    }
};
