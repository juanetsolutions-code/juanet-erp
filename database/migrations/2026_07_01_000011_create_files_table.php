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
        Schema::create('stored_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id');
            $table->string('name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('category')->default('document'); // image, document, video, audio, archive
            $table->string('visibility')->default('private'); // private, public
            $table->boolean('is_temporary')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->string('virus_scan_status')->default('pending'); // pending, clean, infected, skipped
            $table->text('virus_scan_result')->nullable();
            $table->string('hash', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['organization_id', 'user_id']);
            $table->index(['category']);
            $table->index(['visibility']);
            $table->index(['is_temporary', 'expires_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
