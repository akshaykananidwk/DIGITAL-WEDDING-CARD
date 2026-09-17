<?php

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

/** Invitation categories and subcategories (admin can add unlimited). */
return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::create('categories', static function (Blueprint $t): void {
            $t->id();
            $t->string('name', 120);
            $t->string('name_gu', 160)->nullable();
            $t->string('name_hi', 160)->nullable();
            $t->string('slug', 140)->unique();
            $t->string('description', 500)->nullable();
            $t->string('icon', 60)->nullable();
            $t->string('image', 255)->nullable();
            $t->string('color', 9)->default('#C8102E');
            $t->integer('sort_order')->default(0)->index();
            $t->boolean('is_active')->default(1)->index();
            $t->boolean('is_featured')->default(0);
            $t->integer('template_count')->default(0);
            $t->string('meta_title', 191)->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('subcategories', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('category_id')->references('categories', 'id', 'cascade');
            $t->string('name', 120);
            $t->string('name_gu', 160)->nullable();
            $t->string('name_hi', 160)->nullable();
            $t->string('slug', 140)->unique();
            $t->string('description', 500)->nullable();
            $t->string('icon', 60)->nullable();
            $t->string('image', 255)->nullable();
            // Theme hints drive AI template recommendation and search filters.
            $t->json('theme_tags')->nullable();
            $t->integer('sort_order')->default(0)->index();
            $t->boolean('is_active')->default(1)->index();
            $t->boolean('is_featured')->default(0);
            $t->integer('template_count')->default(0);
            $t->string('meta_title', 191)->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['category_id', 'is_active', 'sort_order'], 'idx_subcat_lookup');
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);
        Schema::drop('subcategories');
        Schema::drop('categories');
    }
};
