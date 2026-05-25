<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'talent_id')) {
                $table->foreignId('talent_id')
                    ->nullable()
                    ->after('image')
                    ->constrained('talents')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'is_talent_product')) {
                $table->boolean('is_talent_product')->default(false)->after('talent_id');
            }

            if (! Schema::hasColumn('products', 'external_payment_url')) {
                $table->string('external_payment_url')->nullable()->after('is_talent_product');
            }

            if (! Schema::hasColumn('products', 'external_payment_label')) {
                $table->string('external_payment_label')->nullable()->after('external_payment_url');
            }

            if (! Schema::hasColumn('products', 'stock')) {
                $table->unsignedInteger('stock')->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'talent_id')) {
                $table->dropConstrainedForeignId('talent_id');
            }

            if (Schema::hasColumn('products', 'is_talent_product')) {
                $table->dropColumn('is_talent_product');
            }

            if (Schema::hasColumn('products', 'external_payment_url')) {
                $table->dropColumn('external_payment_url');
            }

            if (Schema::hasColumn('products', 'external_payment_label')) {
                $table->dropColumn('external_payment_label');
            }

            if (Schema::hasColumn('products', 'stock')) {
                $table->dropColumn('stock');
            }
        });
    }
};
