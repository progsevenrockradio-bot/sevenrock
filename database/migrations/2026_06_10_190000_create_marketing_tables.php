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
        Schema::create('marketing_mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('sender_name');
            $table->string('imap_host')->default('imap.gmail.com');
            $table->integer('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');
            $table->text('imap_password')->nullable();
            $table->string('smtp_host')->default('smtp.gmail.com');
            $table->integer('smtp_port')->default(465);
            $table->string('smtp_encryption')->default('ssl');
            $table->text('smtp_password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('company_or_band')->nullable();
            $table->string('role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->string('source_type')->nullable(); // e.g., 'scraped_trash', 'manual'
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->foreign('source_account_id')
                ->references('id')
                ->on('marketing_mail_accounts')
                ->onDelete('set null');
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->unsignedBigInteger('sender_account_id');
            $table->string('template');
            $table->text('body_content');
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->string('status')->default('draft'); // draft, sending, sent, failed
            $table->integer('total_contacts')->default(0);
            $table->integer('sent_contacts')->default(0);
            $table->timestamps();

            $table->foreign('sender_account_id')
                ->references('id')
                ->on('marketing_mail_accounts')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_contacts');
        Schema::dropIfExists('marketing_mail_accounts');
    }
};
