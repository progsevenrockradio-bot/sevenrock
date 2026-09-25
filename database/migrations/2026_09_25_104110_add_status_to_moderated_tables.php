<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['events', 'talent_media', 'products', 'talent_albums'];
        
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'status')) {
                    $table->string('status')->default('approved');
                }
            });
        }

        Schema::table('talent_interactions', function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'approved')) {
                $table->boolean('approved')->default(true);
            }
        });
        
        Schema::table('community_posts', function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'status')) {
                $table->string('status')->default('approved');
            }
        });
    }

    public function down(): void
    {
        $tables = ['events', 'talent_media', 'products', 'talent_albums'];
        
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'status')) {
                    $table->dropColumn('status');
                }
            });
        }

        Schema::table('talent_interactions', function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'approved')) {
                $table->dropColumn('approved');
            }
        });
        
        Schema::table('community_posts', function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
