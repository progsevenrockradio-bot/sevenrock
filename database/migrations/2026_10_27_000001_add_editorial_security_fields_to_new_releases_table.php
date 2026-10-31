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
        Schema::table('new_releases', function (Blueprint $table) {
            if (! Schema::hasColumn('new_releases', 'is_embargo_active')) {
                $table->boolean('is_embargo_active')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('new_releases', 'embargo_until')) {
                $table->timestamp('embargo_until')->nullable()->after('is_embargo_active');
            }
            if (! Schema::hasColumn('new_releases', 'source_subject')) {
                $table->string('source_subject', 500)->nullable()->after('author_email');
            }
            if (! Schema::hasColumn('new_releases', 'source_message_id')) {
                $table->string('source_message_id', 255)->nullable()->after('source_subject');
            }
            if (! Schema::hasColumn('new_releases', 'security_status')) {
                $table->string('security_status', 50)->default('passed')->after('source_message_id');
            }
            if (! Schema::hasColumn('new_releases', 'incident_reason')) {
                $table->text('incident_reason')->nullable()->after('security_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_releases', function (Blueprint $table) {
            $cols = ['is_embargo_active', 'embargo_until', 'source_subject', 'source_message_id', 'security_status', 'incident_reason'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('new_releases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

