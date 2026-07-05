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
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('product_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('category_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('technology')->nullable();
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->integer('review_count')->default(0);
            $table->decimal('price', 12, 2)->default(0.00);
            $table->decimal('previous_price', 12, 2)->nullable();
            $table->boolean('is_new')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('thumbnail')->nullable();
            $table->jsonb('gallery')->nullable();
            $table->jsonb('features')->nullable();
            $table->jsonb('screenshots')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('marketplace_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_categories');
    }
};
