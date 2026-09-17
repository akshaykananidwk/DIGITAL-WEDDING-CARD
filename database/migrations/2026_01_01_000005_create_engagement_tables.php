<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

/**
 * Analytics and RSVP.
 *
 * Raw event rows power "today"/"last 7 days"; a nightly aggregate table keeps
 * the all-time charts fast once an invitation has been seen a million times.
 * No raw IP address is ever stored - only a salted hash.
 */
return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('invitation_views', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->string('visitor_hash', 32)->index();
            $t->enum('device_type', ['mobile', 'tablet', 'desktop', 'bot', 'unknown'])->default('unknown');
            $t->string('browser', 30)->nullable();
            $t->string('referrer_host', 120)->nullable();
            $t->string('country', 2)->nullable();
            $t->boolean('is_unique')->default(0);
            $t->dateTime('viewed_at')->index();
            $t->index(['invitation_id', 'viewed_at'], 'idx_invview_window');
        });

        Schema::create('invitation_shares', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->enum('channel', ['whatsapp', 'facebook', 'telegram', 'x', 'email', 'copy', 'qr', 'other'])
                ->default('other')->index();
            $t->string('visitor_hash', 32)->nullable();
            $t->dateTime('shared_at')->index();
            $t->index(['invitation_id', 'shared_at'], 'idx_invshare_window');
        });

        Schema::create('invitation_downloads', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->enum('kind', ['pdf', 'pdf_mobile', 'qr_png', 'qr_svg', 'ics', 'image'])->default('pdf')->index();
            $t->string('visitor_hash', 32)->nullable();
            $t->dateTime('downloaded_at')->index();
            $t->index(['invitation_id', 'downloaded_at'], 'idx_invdl_window');
        });

        Schema::create('invitation_daily_stats', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->date('stat_date');
            $t->integer('views')->default(0);
            $t->integer('unique_views')->default(0);
            $t->integer('shares')->default(0);
            $t->integer('downloads')->default(0);
            $t->integer('qr_scans')->default(0);
            $t->integer('rsvps')->default(0);
            $t->json('devices')->nullable();
            $t->timestamps();
            $t->unique(['invitation_id', 'stat_date'], 'unq_invdaily');
            $t->index(['stat_date'], 'idx_invdaily_date');
        });

        Schema::create('rsvp', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->string('name', 120);
            $t->string('phone', 20)->nullable();
            $t->string('email', 191)->nullable();
            $t->enum('response', ['yes', 'maybe', 'no'])->default('yes')->index();
            $t->integer('guests')->default(1);
            $t->text('message')->nullable();
            $t->string('visitor_hash', 32)->nullable();
            $t->string('ip_hash', 32)->nullable();
            $t->boolean('is_read')->default(0);
            $t->timestamps();
            $t->index(['invitation_id', 'response'], 'idx_rsvp_response');
            $t->index(['invitation_id', 'created_at'], 'idx_rsvp_recent');
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        foreach ([
            'rsvp', 'invitation_daily_stats', 'invitation_downloads',
            'invitation_shares', 'invitation_views',
        ] as $table) {
            Schema::drop($table);
        }
    }
};
