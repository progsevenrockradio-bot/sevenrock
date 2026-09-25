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
        Schema::table('marketing_contacts', function (Blueprint $table) {
            $table->timestamp('unsubscribed_at')->nullable()->after('is_active');
            $table->string('unsubscribe_token', 64)->nullable()->index()->after('unsubscribed_at');
        });

        foreach (\App\Models\MarketingContact::all() as $contact) {
            $contact->update(['unsubscribe_token' => bin2hex(random_bytes(32))]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_contacts', function (Blueprint $table) {
            $table->dropColumn(['unsubscribed_at', 'unsubscribe_token']);
        });
    }
};
