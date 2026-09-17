<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

/** User invitations and the content that fills a template. */
return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('invitations', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->references('users', 'id', 'cascade');
            $t->foreignId('template_id')->references('templates', 'id', 'restrict');
            $t->foreignId('category_id')->nullable()->references('categories', 'id', 'set null');
            $t->foreignId('subcategory_id')->nullable()->references('subcategories', 'id', 'set null');
            $t->string('title', 191);
            // Pretty URL: /invite/rahul-weds-priya
            $t->string('slug', 191)->unique();
            // Short URL: /i/8F3K9A
            $t->string('short_code', 12)->unique();
            $t->string('event_type', 60)->nullable();
            $t->date('event_date')->nullable()->index();
            $t->time('event_time')->nullable();
            // Denormalised for the countdown and the "upcoming" cron job.
            $t->dateTime('event_at')->nullable()->index();
            $t->string('timezone', 40)->default('Asia/Kolkata');
            $t->string('language', 10)->default('en');
            $t->enum('status', ['draft', 'published', 'unpublished', 'archived'])->default('draft')->index();
            $t->integer('wizard_step')->default(1);
            // Per-invitation colour/font overrides, respecting template limits.
            $t->json('theme_overrides')->nullable();
            // Toggles: music, countdown, rsvp, gallery, animation, watermark…
            $t->json('settings')->nullable();
            // Optional passphrase for a private invitation.
            $t->string('access_password', 255)->nullable();
            $t->string('meta_title', 191)->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->string('og_image', 255)->nullable();
            $t->string('qr_path', 255)->nullable();
            $t->integer('view_count')->default(0);
            $t->integer('unique_view_count')->default(0);
            $t->integer('share_count')->default(0);
            $t->integer('download_count')->default(0);
            $t->integer('qr_scan_count')->default(0);
            $t->integer('rsvp_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamp('last_viewed_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['user_id', 'status', 'created_at'], 'idx_inv_user');
            $t->index(['status', 'published_at'], 'idx_inv_public');
            $t->index(['template_id'], 'idx_inv_template');
        });

        Schema::create('invitation_data', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->string('field_key', 64);
            $t->longText('value')->nullable();
            // Structured values (galleries, repeaters, social links).
            $t->json('value_json')->nullable();
            $t->timestamps();
            $t->unique(['invitation_id', 'field_key'], 'unq_invdata');
        });

        Schema::create('invitation_sections', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->string('section_key', 64);
            $t->string('title', 191)->nullable();
            $t->boolean('is_visible')->default(1);
            $t->integer('sort_order')->default(0);
            $t->json('config')->nullable();
            $t->timestamps();
            $t->unique(['invitation_id', 'section_key'], 'unq_invsection');
        });

        Schema::create('invitation_photos', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->string('path', 255);
            $t->string('thumb_path', 255)->nullable();
            $t->string('webp_path', 255)->nullable();
            $t->string('caption', 191)->nullable();
            $t->string('role', 30)->default('gallery');
            $t->integer('width')->default(0);
            $t->integer('height')->default(0);
            $t->unsignedBigInteger('size')->default(0);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->index(['invitation_id', 'role', 'sort_order'], 'idx_invphoto_order');
        });

        Schema::create('invitation_music', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invitation_id')->references('invitations', 'id', 'cascade');
            $t->string('path', 255);
            $t->string('title', 191)->nullable();
            $t->string('source', 20)->default('library');
            $t->boolean('autoplay')->default(0);
            $t->boolean('loop_track')->default(1);
            $t->integer('volume')->default(70);
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
            $t->index(['invitation_id'], 'idx_invmusic');
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        foreach ([
            'invitation_music', 'invitation_photos', 'invitation_sections',
            'invitation_data', 'invitations',
        ] as $table) {
            Schema::drop($table);
        }
    }
};
