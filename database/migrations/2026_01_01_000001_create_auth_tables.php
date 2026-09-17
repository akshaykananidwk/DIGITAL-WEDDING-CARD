<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

/** Roles, permissions, users, sessions, password resets, API tokens. */
return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('roles', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 80)->unique();
            $t->string('description', 255)->nullable();
            // Higher level = more authority. Used to stop an admin editing a
            // super-admin account.
            $t->integer('level')->default(10);
            $t->boolean('is_system')->default(0);
            $t->timestamps();
            $t->tableComment('RBAC roles');
        });

        Schema::create('permissions', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 120)->unique();
            $t->string('group_name', 60)->default('general')->index();
            $t->string('description', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('role_permissions', static function (Blueprint $t): void {
            $t->foreignId('role_id')->references('roles', 'id', 'cascade');
            $t->foreignId('permission_id')->references('permissions', 'id', 'cascade');
            $t->primaryKey(['role_id', 'permission_id']);
        });

        Schema::create('plans', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 60)->unique();
            $t->string('description', 255)->nullable();
            $t->decimal('price_monthly', 10, 2)->default(0);
            $t->decimal('price_yearly', 10, 2)->default(0);
            $t->string('currency', 3)->default('INR');
            // Limits are data, not code: max_invitations, storage_mb, ai_credits…
            $t->json('limits')->nullable();
            $t->json('features')->nullable();
            $t->boolean('is_active')->default(1);
            $t->boolean('is_default')->default(0);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->tableComment('Monetisation ready: every plan is free until enabled');
        });

        Schema::create('users', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 191)->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('phone', 20)->nullable()->index();
            $t->string('password', 255);
            $t->foreignId('role_id')->references('roles', 'id', 'restrict');
            $t->foreignId('plan_id')->nullable()->references('plans', 'id', 'set null');
            $t->enum('status', ['active', 'pending', 'suspended'])->default('active')->index();
            $t->string('locale', 5)->default('en');
            $t->string('avatar', 255)->nullable();
            $t->string('city', 80)->nullable();
            $t->unsignedBigInteger('storage_used')->default(0);
            $t->integer('ai_credits')->default(0);
            $t->integer('invitation_count')->default(0);
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip_hash', 32)->nullable();
            $t->string('remember_selector', 24)->nullable()->unique();
            $t->string('remember_validator', 64)->nullable();
            $t->unsignedBigInteger('remember_expires')->nullable();
            $t->string('verify_token', 64)->nullable()->index();
            $t->json('preferences')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['created_at']);
        });

        Schema::create('user_roles', static function (Blueprint $t): void {
            // Secondary roles. users.role_id remains the primary role.
            $t->foreignId('user_id')->references('users', 'id', 'cascade');
            $t->foreignId('role_id')->references('roles', 'id', 'cascade');
            $t->primaryKey(['user_id', 'role_id']);
        });

        Schema::create('password_resets', static function (Blueprint $t): void {
            $t->id();
            $t->string('email', 191)->index();
            $t->string('selector', 32)->unique();
            $t->string('token_hash', 64);
            $t->unsignedBigInteger('expires_at')->index();
            $t->timestamp('used_at')->nullable();
            $t->string('ip_hash', 32)->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', static function (Blueprint $t): void {
            $t->string('id', 128);
            $t->primaryKey(['id']);
            $t->foreignId('user_id')->nullable()->references('users', 'id', 'set null');
            $t->string('ip_hash', 32)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->longText('payload')->nullable();
            $t->unsignedBigInteger('last_activity')->default(0)->index();
        });

        Schema::create('api_tokens', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->references('users', 'id', 'cascade');
            $t->string('name', 80);
            // Only the hash is stored; the plaintext is shown once.
            $t->string('token_hash', 64)->unique();
            $t->json('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        foreach ([
            'api_tokens', 'sessions', 'password_resets', 'user_roles',
            'users', 'plans', 'role_permissions', 'permissions', 'roles',
        ] as $table) {
            Schema::drop($table);
        }
    }
};
