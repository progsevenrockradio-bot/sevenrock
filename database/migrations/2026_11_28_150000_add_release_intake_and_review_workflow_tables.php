<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('new_releases')) {
            Schema::table('new_releases', function (Blueprint $table): void {
                if (! Schema::hasColumn('new_releases', 'release_type')) {
                    $table->string('release_type', 32)->nullable()->after('artist_name');
                }
                if (! Schema::hasColumn('new_releases', 'announced_at')) {
                    $table->date('announced_at')->nullable()->after('released_at');
                }
                if (! Schema::hasColumn('new_releases', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('announced_at')->index();
                }
                if (! Schema::hasColumn('new_releases', 'review_status')) {
                    $table->string('review_status', 32)->nullable()->after('is_active')->index();
                }
                if (! Schema::hasColumn('new_releases', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('review_status');
                }
                if (! Schema::hasColumn('new_releases', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('rejection_reason')->index();
                }
                if (! Schema::hasColumn('new_releases', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }
                if (! Schema::hasColumn('new_releases', 'received_at')) {
                    $table->timestamp('received_at')->nullable()->after('reviewed_at');
                }
                if (! Schema::hasColumn('new_releases', 'source_sender_email')) {
                    $table->string('source_sender_email', 255)->nullable()->after('source_message_id')->index();
                }
                if (! Schema::hasColumn('new_releases', 'source_sender_auth')) {
                    $table->json('source_sender_auth')->nullable()->after('source_sender_email');
                }
                if (! Schema::hasColumn('new_releases', 'proposal_summary')) {
                    $table->text('proposal_summary')->nullable()->after('description');
                }
                if (! Schema::hasColumn('new_releases', 'cover_source')) {
                    $table->string('cover_source', 255)->nullable()->after('cover_image');
                }
                if (! Schema::hasColumn('new_releases', 'cover_permission_credit')) {
                    $table->string('cover_permission_credit', 255)->nullable()->after('cover_source');
                }
                if (! Schema::hasColumn('new_releases', 'official_url')) {
                    $table->string('official_url', 1000)->nullable()->after('spotify_url');
                }
                if (! Schema::hasColumn('new_releases', 'download_url')) {
                    $table->string('download_url', 1000)->nullable()->after('official_url');
                }
                if (! Schema::hasColumn('new_releases', 'usage_conditions')) {
                    $table->text('usage_conditions')->nullable()->after('embargo_until');
                }
                if (! Schema::hasColumn('new_releases', 'intake_metadata')) {
                    $table->json('intake_metadata')->nullable()->after('usage_conditions');
                }
            });
        }

        if (! Schema::hasTable('release_intake_settings')) {
            Schema::create('release_intake_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('release_intake_senders')) {
            Schema::create('release_intake_senders', function (Blueprint $table): void {
                $table->id();
                $table->string('email', 255)->unique();
                $table->string('entity_name', 255);
                $table->string('entity_type', 32)->default('artist');
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('release_intake_senders');
        Schema::dropIfExists('release_intake_settings');

        if (Schema::hasTable('new_releases')) {
            Schema::table('new_releases', function (Blueprint $table): void {
                $cols = [
                    'release_type',
                    'announced_at',
                    'published_at',
                    'review_status',
                    'rejection_reason',
                    'reviewed_by',
                    'reviewed_at',
                    'received_at',
                    'source_sender_email',
                    'source_sender_auth',
                    'proposal_summary',
                    'cover_source',
                    'cover_permission_credit',
                    'official_url',
                    'download_url',
                    'usage_conditions',
                    'intake_metadata',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('new_releases', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

