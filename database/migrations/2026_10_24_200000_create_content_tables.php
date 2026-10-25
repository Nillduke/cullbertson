<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('pages', function (Blueprint $table) {
            $table->id(); $table->string('slug')->unique(); $table->string('title');
            $table->string('type')->default('page')->index(); $table->string('status')->default('draft')->index();
            $table->text('description')->nullable(); $table->string('featured_image')->nullable();
            $table->json('categories')->nullable(); $table->string('article_category')->nullable();
            $table->json('presentation')->nullable(); $table->timestamp('published_at')->nullable(); $table->timestamps();
        });
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id(); $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('component')->default('html');
            $table->unsignedInteger('position')->default(0); $table->longText('content_html'); $table->timestamps();
            $table->index(['page_id', 'position']);
        });
        Schema::create('site_blocks', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->string('name'); $table->longText('content_html'); $table->timestamps();
        });
        Schema::create('url_redirects', function (Blueprint $table) {
            $table->id(); $table->string('slug')->unique(); $table->string('destination'); $table->timestamps();
        });
        Schema::create('form_definitions', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->string('name'); $table->json('definition'); $table->timestamps();
        });
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id(); $table->string('reference')->unique(); $table->string('service')->index();
            $table->json('payload'); $table->string('status')->default('new'); $table->timestamps();
        });
        Schema::create('feedback_entries', function (Blueprint $table) {
            $table->id(); $table->string('reference')->unique(); $table->json('payload');
            $table->string('status')->default('new'); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['feedback_entries','quote_requests','form_definitions','url_redirects','site_blocks','page_sections','pages'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
