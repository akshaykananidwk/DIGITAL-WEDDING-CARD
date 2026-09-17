<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

/**
 * The template engine tables.
 *
 * A template is *data*: a layout key (which renderer draws it), a theme
 * (colours/fonts/ornaments) and a set of dynamic fields. Adding a thousand
 * templates therefore means inserting a thousand rows, not writing code.
 */
return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('templates', static function (Blueprint $t): void {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 160);
            $t->string('slug', 180)->unique();
            $t->text('description')->nullable();
            $t->foreignId('category_id')->references('categories', 'id', 'restrict');
            $t->foreignId('subcategory_id')->nullable()->references('subcategories', 'id', 'set null');
            $t->enum('type', ['static', 'multi_page', 'animated', 'three_d', 'video', 'interactive', 'kankotri'])
                ->default('static')->index();
            // Which renderer in app/Views/invite/layouts draws this template.
            $t->string('layout_key', 60)->default('classic-kankotri')->index();
            $t->string('language', 10)->default('multi')->index();
            $t->string('orientation', 12)->default('portrait');
            $t->integer('canvas_width')->default(1080);
            $t->integer('canvas_height')->default(1920);
            $t->integer('page_count')->default(1);
            // Design tokens: colours, fonts, ornament set, background, motion.
            $t->json('theme')->nullable();
            // Optional fully custom markup authored in the admin template builder.
            $t->mediumText('custom_html')->nullable();
            $t->mediumText('custom_css')->nullable();
            $t->mediumText('custom_js')->nullable();
            $t->string('thumbnail', 255)->nullable();
            $t->json('preview_images')->nullable();
            $t->json('demo_data')->nullable();
            $t->json('tags')->nullable();
            $t->string('search_keywords', 500)->nullable();
            $t->string('color_primary', 9)->default('#C8102E');
            $t->string('color_secondary', 9)->default('#F0B429');
            $t->string('color_background', 9)->default('#FFF8EE');
            $t->string('font_heading', 60)->default('GreatVibes');
            $t->string('font_body', 60)->default('NotoSans');
            $t->boolean('supports_music')->default(1);
            $t->boolean('supports_gallery')->default(1);
            $t->boolean('supports_countdown')->default(1);
            $t->boolean('supports_rsvp')->default(1);
            $t->boolean('supports_map')->default(1);
            $t->boolean('has_animation')->default(0)->index();
            $t->boolean('is_premium')->default(0)->index();
            $t->boolean('is_active')->default(1)->index();
            $t->boolean('is_featured')->default(0)->index();
            $t->integer('sort_order')->default(0);
            $t->integer('view_count')->default(0);
            $t->integer('use_count')->default(0)->index();
            $t->decimal('rating', 3, 2)->default(0);
            $t->string('meta_title', 191)->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->string('og_image', 255)->nullable();
            $t->foreignId('created_by')->nullable()->references('users', 'id', 'set null');
            $t->timestamps();
            $t->softDeletes();

            // The indexes that keep the gallery fast with 10k+ rows.
            $t->index(['category_id', 'is_active', 'sort_order'], 'idx_tpl_category');
            $t->index(['subcategory_id', 'is_active'], 'idx_tpl_subcategory');
            $t->index(['is_active', 'is_featured', 'use_count'], 'idx_tpl_popular');
            $t->fullText(['name', 'search_keywords'], 'ft_tpl_search');
        });

        Schema::create('template_fields', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('template_id')->references('templates', 'id', 'cascade');
            $t->string('field_key', 64);
            $t->string('label', 160);
            $t->string('label_gu', 191)->nullable();
            $t->string('label_hi', 191)->nullable();
            $t->enum('type', [
                'text', 'textarea', 'richtext', 'date', 'time', 'datetime', 'number',
                'image', 'gallery', 'phone', 'email', 'url', 'location', 'color',
                'font', 'select', 'multiselect', 'checkbox', 'social', 'music',
            ])->default('text');
            $t->string('section', 60)->default('main')->index();
            $t->string('placeholder', 191)->nullable();
            $t->string('help_text', 300)->nullable();
            $t->text('default_value')->nullable();
            $t->json('options')->nullable();
            $t->string('validation', 191)->nullable();
            $t->integer('max_length')->nullable();
            $t->boolean('is_required')->default(0);
            $t->boolean('is_editable')->default(1);
            $t->boolean('is_visible')->default(1);
            $t->boolean('is_ai_generatable')->default(0);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['template_id', 'field_key'], 'unq_tplfield');
            $t->index(['template_id', 'sort_order'], 'idx_tplfield_order');
        });

        Schema::create('template_components', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('template_id')->references('templates', 'id', 'cascade');
            $t->foreignId('parent_id')->nullable()->references('template_components', 'id', 'cascade');
            $t->string('component_key', 64);
            $t->string('name', 120);
            $t->enum('type', [
                'page', 'section', 'heading', 'text', 'names', 'divider', 'image',
                'gallery', 'countdown', 'map', 'rsvp', 'family', 'events', 'quote',
                'ornament', 'music', 'share', 'qr', 'custom',
            ])->default('section');
            // Markup may contain {{field_key}} placeholders.
            $t->mediumText('content')->nullable();
            $t->json('styles')->nullable();
            $t->json('props')->nullable();
            $t->integer('page_number')->default(1);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_visible')->default(1);
            $t->boolean('is_toggleable')->default(1);
            $t->timestamps();
            $t->index(['template_id', 'page_number', 'sort_order'], 'idx_tplcomp_order');
        });

        Schema::create('template_assets', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('template_id')->references('templates', 'id', 'cascade');
            $t->enum('kind', ['image', 'font', 'audio', 'video', 'svg', 'css', 'js'])->default('image');
            $t->string('path', 255);
            $t->string('original_name', 191)->nullable();
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->json('meta')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->index(['template_id', 'kind'], 'idx_tplasset_kind');
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        foreach (['template_assets', 'template_components', 'template_fields', 'templates'] as $table) {
            Schema::drop($table);
        }
    }
};
