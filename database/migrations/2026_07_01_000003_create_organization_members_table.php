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
        Schema::create('organization_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('organization_id');
            $table->uuid('user_id');
            
            $table->boolean('is_owner')->default(false);
            $table->string('status', 50)->default('active'); // active, suspended, pending
            
            // Optimistic Locking Column
            $table->unsignedInteger('version')->default(1);
            
            // Audit Columns
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys with proper cascade deletes and constraints
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
                
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Unique composite index for exact membership constraint
            $table->unique(['organization_id', 'user_id']);
            
            // Status and tracking index
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
