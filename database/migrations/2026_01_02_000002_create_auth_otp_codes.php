<?php

/**
 * One-time codes for a second login factor.
 *
 * Only the hash of a code is stored, it can be used once, and the attempt
 * counter is on the row rather than in the session so a new session cannot be
 * used to reset it.
 */

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('auth_otp_codes', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->references('users', 'id', 'cascade');
            // login, or a future purpose such as confirming a phone number.
            $t->string('purpose', 40)->default('login');
            $t->string('channel', 20)->default('email');
            $t->string('code_hash', 255);
            $t->string('sent_to', 191)->nullable();
            $t->integer('attempts')->default(0);
            $t->string('ip_hash', 32)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'purpose', 'expires_at'], 'idx_otp_lookup');
        });

        // Per-user opt-in. The platform default stays off.
        Schema::addColumn('users', 'two_factor_enabled', static function (Blueprint $t): void {
            $t->boolean('two_factor_enabled')->default(0);
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        Schema::drop('auth_otp_codes');

        if (!$db->isSqlite() && $db->columnExists('users', 'two_factor_enabled')) {
            $db->pdo()->exec(
                'ALTER TABLE ' . $db->wrap($db->table('users')) . ' DROP COLUMN '
                . $db->wrap('two_factor_enabled')
            );
        }
    }
};
