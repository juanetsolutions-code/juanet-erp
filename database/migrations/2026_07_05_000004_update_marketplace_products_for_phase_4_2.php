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
        Schema::table('marketplace_products', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_products', 'framework')) {
                $table->string('framework')->nullable();
            }
            if (!Schema::hasColumn('marketplace_products', 'version')) {
                $table->string('version')->default('1.0.0');
            }
            if (!Schema::hasColumn('marketplace_products', 'downloads')) {
                $table->integer('downloads')->default(0);
            }
            if (!Schema::hasColumn('marketplace_products', 'views')) {
                $table->integer('views')->default(0);
            }
            if (!Schema::hasColumn('marketplace_products', 'author')) {
                $table->string('author')->nullable();
            }
            if (!Schema::hasColumn('marketplace_products', 'license')) {
                $table->string('license')->default('Regular License');
            }
            if (!Schema::hasColumn('marketplace_products', 'tags')) {
                $table->jsonb('tags')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropColumn(['framework', 'version', 'downloads', 'views', 'author', 'license', 'tags']);
        });
    }
};
