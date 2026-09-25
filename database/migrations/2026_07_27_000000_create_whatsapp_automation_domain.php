<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role', 30)->default('agent')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->index(['workspace_id', 'role']);
        });

        Schema::create('whatsapp_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('phone_number_id')->nullable()->unique();
            $table->string('waba_id')->nullable();
            $table->string('display_phone_number')->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('verify_token')->nullable();
            $table->string('graph_version', 20)->default('v25.0');
            $table->string('status', 30)->default('disconnected');
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('trigger_type', 40)->default('incoming_message');
            $table->json('trigger_config')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('automation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 20)->default('draft');
            $table->json('graph');
            $table->json('validation_result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->unique(['automation_id', 'version']);
            $table->index(['automation_id', 'status']);
        });

        Schema::table('automations', function (Blueprint $table) {
            $table->foreignId('published_version_id')
                ->nullable()
                ->after('trigger_config')
                ->constrained('automation_versions')
                ->nullOnDelete();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('wa_id');
            $table->string('phone_number');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('opt_in')->default(true);
            $table->timestampTz('opted_in_at')->nullable();
            $table->timestampTz('opted_out_at')->nullable();
            $table->json('attributes')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'wa_id']);
            $table->index(['workspace_id', 'phone_number']);
            $table->index(['workspace_id', 'last_seen_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('status', 20)->default('bot');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('automation_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_node_id')->nullable();
            $table->json('context')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestampTz('customer_service_window_expires_at')->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_channel_id', 'contact_id']);
            $table->index(['workspace_id', 'status', 'last_message_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10);
            $table->string('type', 30)->default('text');
            $table->string('status', 20)->default('received');
            $table->string('meta_message_id')->nullable()->unique();
            $table->string('reply_to_meta_message_id')->nullable();
            $table->json('content');
            $table->json('error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('flow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_version_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('status', 20)->default('running');
            $table->string('current_node_id')->nullable();
            $table->json('context')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'status']);
            $table->index(['automation_version_id', 'started_at']);
        });

        Schema::create('flow_execution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_execution_id')->constrained()->cascadeOnDelete();
            $table->string('node_id')->nullable();
            $table->string('event_type', 40);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['flow_execution_id', 'created_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_id', 64)->unique();
            $table->string('status', 20)->default('received');
            $table->json('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'received_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['workspace_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('flow_execution_events');
        Schema::dropIfExists('flow_executions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('contacts');

        Schema::table('automations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_version_id');
        });

        Schema::dropIfExists('automation_versions');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('whatsapp_channels');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropColumn(['role', 'is_active']);
        });

        Schema::dropIfExists('workspaces');
    }
};
