<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

/** Settings, media, fonts, pages, AI, audit, backups, updates, health, flags. */
return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('settings', static function (Blueprint $t): void {
            $t->id();
            $t->string('group_name', 60)->default('general')->index();
            $t->string('setting_key', 120)->unique();
            $t->longText('setting_value')->nullable();
            // 'encrypted' values are sealed with the application key at rest.
            $t->enum('value_type', ['string', 'text', 'integer', 'boolean', 'json', 'encrypted'])->default('string');
            $t->boolean('is_public')->default(0);
            $t->string('label', 191)->nullable();
            $t->string('description', 300)->nullable();
            $t->foreignId('updated_by')->nullable()->references('users', 'id', 'set null');
            $t->timestamps();
        });

        Schema::create('feature_flags', static function (Blueprint $t): void {
            $t->id();
            $t->string('flag_key', 80)->unique();
            $t->string('name', 120);
            $t->string('description', 300)->nullable();
            $t->boolean('is_enabled')->default(0);
            // Percentage rollout for gradual releases (0-100).
            $t->integer('rollout')->default(100);
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('media', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->references('users', 'id', 'set null');
            $t->string('folder', 120)->default('general')->index();
            $t->string('path', 191)->unique();
            $t->string('thumb_path', 191)->nullable();
            $t->string('webp_path', 191)->nullable();
            $t->string('original_name', 191)->nullable();
            $t->string('mime', 100)->nullable();
            $t->string('extension', 12)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->integer('width')->default(0);
            $t->integer('height')->default(0);
            $t->enum('kind', ['image', 'audio', 'font', 'video', 'document'])->default('image')->index();
            $t->string('alt_text', 191)->nullable();
            $t->string('title', 191)->nullable();
            $t->integer('usage_count')->default(0);
            $t->boolean('is_public')->default(1);
            $t->boolean('is_library')->default(0)->index();
            // Content hash: lets the library dedupe identical uploads.
            $t->string('content_hash', 64)->nullable()->index();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['kind', 'created_at'], 'idx_media_recent');
        });

        Schema::create('fonts', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 120)->unique();
            $t->string('family', 120);
            $t->enum('source', ['bundled', 'uploaded', 'google', 'system'])->default('bundled');
            $t->string('file_path', 255)->nullable();
            $t->string('css_url', 255)->nullable();
            $t->string('weight', 12)->default('400');
            $t->string('style', 12)->default('normal');
            $t->enum('script', ['latin', 'gujarati', 'devanagari', 'multi'])->default('latin')->index();
            $t->string('category', 30)->default('sans');
            $t->string('preview_text', 191)->nullable();
            $t->boolean('pdf_capable')->default(0);
            $t->boolean('is_active')->default(1)->index();
            $t->boolean('is_default')->default(0);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('pages', static function (Blueprint $t): void {
            $t->id();
            $t->string('title', 191);
            $t->string('slug', 191)->unique();
            $t->longText('content')->nullable();
            $t->string('excerpt', 500)->nullable();
            $t->string('meta_title', 191)->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->enum('status', ['draft', 'published'])->default('published')->index();
            $t->boolean('show_in_footer')->default(1);
            $t->boolean('show_in_header')->default(0);
            $t->integer('sort_order')->default(0);
            $t->string('locale', 5)->default('en');
            $t->foreignId('updated_by')->nullable()->references('users', 'id', 'set null');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('ai_settings', static function (Blueprint $t): void {
            $t->id();
            $t->string('provider', 40)->default('gemini');
            // Sealed with the application key; never rendered in full.
            $t->text('api_key')->nullable();
            $t->string('model', 80)->default('gemini-2.0-flash');
            $t->string('endpoint', 255)->nullable();
            $t->decimal('temperature', 3, 2)->default(0.70);
            $t->decimal('top_p', 3, 2)->default(0.95);
            $t->integer('max_tokens')->default(2048);
            $t->integer('timeout')->default(30);
            $t->integer('daily_limit')->default(100);
            $t->text('system_prompt')->nullable();
            $t->boolean('is_enabled')->default(0);
            $t->boolean('allow_recommendations')->default(1);
            $t->foreignId('updated_by')->nullable()->references('users', 'id', 'set null');
            $t->timestamps();
        });

        Schema::create('ai_logs', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->references('users', 'id', 'set null');
            $t->foreignId('invitation_id')->nullable()->references('invitations', 'id', 'set null');
            $t->string('action', 60)->index();
            $t->string('model', 80)->nullable();
            $t->string('locale', 10)->nullable();
            $t->integer('prompt_tokens')->default(0);
            $t->integer('completion_tokens')->default(0);
            $t->integer('total_tokens')->default(0);
            $t->integer('latency_ms')->default(0);
            $t->enum('status', ['success', 'error', 'blocked', 'quota'])->default('success')->index();
            $t->string('error_message', 500)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'created_at'], 'idx_ailog_user');
        });

        Schema::create('audit_logs', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->references('users', 'id', 'set null');
            $t->string('actor_name', 120)->nullable();
            $t->string('action', 80)->index();
            $t->string('entity_type', 60)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('description', 500)->nullable();
            $t->json('changes')->nullable();
            $t->string('ip_hash', 32)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->nullable()->index();
            $t->index(['entity_type', 'entity_id'], 'idx_audit_entity');
        });

        Schema::create('notifications', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->references('users', 'id', 'cascade');
            $t->string('type', 60)->default('info');
            $t->string('title', 191);
            $t->string('body', 500)->nullable();
            $t->string('url', 255)->nullable();
            $t->string('icon', 40)->nullable();
            $t->boolean('is_read')->default(0);
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'is_read', 'created_at'], 'idx_notify_inbox');
        });

        Schema::create('backups', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 191);
            $t->enum('type', ['full', 'database', 'files', 'update'])->default('database')->index();
            $t->string('path', 255)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->integer('table_count')->default(0);
            $t->integer('file_count')->default(0);
            $t->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')->index();
            $t->string('checksum', 64)->nullable();
            $t->string('note', 300)->nullable();
            $t->string('app_version', 20)->nullable();
            $t->foreignId('created_by')->nullable()->references('users', 'id', 'set null');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('error_message', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('update_logs', static function (Blueprint $t): void {
            $t->id();
            $t->string('from_version', 20)->nullable();
            $t->string('to_version', 20)->nullable();
            $t->string('repository', 191)->nullable();
            $t->string('branch', 120)->nullable();
            $t->string('commit_sha', 64)->nullable();
            $t->string('commit_message', 500)->nullable();
            $t->string('commit_author', 120)->nullable();
            $t->enum('status', [
                'checking', 'downloading', 'backing_up', 'staging', 'updating',
                'migrating', 'testing', 'success', 'failed', 'rolled_back',
            ])->default('checking')->index();
            $t->string('step', 60)->nullable();
            $t->string('backup_path', 255)->nullable();
            $t->json('changed_files')->nullable();
            $t->json('migration_result')->nullable();
            $t->json('health_result')->nullable();
            $t->json('steps_log')->nullable();
            $t->integer('duration_ms')->default(0);
            $t->text('error_message')->nullable();
            $t->foreignId('initiated_by')->nullable()->references('users', 'id', 'set null');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        Schema::create('system_health_logs', static function (Blueprint $t): void {
            $t->id();
            $t->enum('status', ['healthy', 'warning', 'critical'])->default('healthy')->index();
            $t->string('php_version', 20)->nullable();
            $t->string('db_version', 60)->nullable();
            $t->string('app_version', 20)->nullable();
            $t->unsignedBigInteger('disk_free')->default(0);
            $t->unsignedBigInteger('disk_total')->default(0);
            $t->json('results')->nullable();
            $t->string('note', 300)->nullable();
            $t->string('context', 40)->default('manual');
            $t->timestamp('checked_at')->nullable()->index();
        });

        Schema::create('cron_runs', static function (Blueprint $t): void {
            $t->id();
            $t->string('task', 60)->index();
            $t->enum('status', ['success', 'failed', 'skipped'])->default('success');
            $t->text('output')->nullable();
            $t->integer('duration_ms')->default(0);
            $t->timestamp('ran_at')->nullable()->index();
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        foreach ([
            'cron_runs', 'system_health_logs', 'update_logs', 'backups',
            'notifications', 'audit_logs', 'ai_logs', 'ai_settings',
            'pages', 'fonts', 'media', 'feature_flags', 'settings',
        ] as $table) {
            Schema::drop($table);
        }
    }
};
