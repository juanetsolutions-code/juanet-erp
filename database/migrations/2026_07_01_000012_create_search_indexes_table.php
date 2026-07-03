<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pg_trgm extension if supported by DB permissions
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Throwable $e) {
            // Log or ignore if permission denied in cloud environment
        }

        // Detect if pgvector extension is available/supported
        $hasPgVector = false;
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            $hasPgVector = true;
        } catch (\Throwable $e) {
            // pgvector extension not installed or active on PostgreSQL server, fallback to JSON
        }

        Schema::create('search_indexes', function (Blueprint $table) use ($hasPgVector) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            
            $table->string('searchable_type', 255)->nullable();
            $table->string('searchable_id', 255)->nullable();
            
            $table->string('module', 50); // crm, marketplace, cms, projects, finance, support, ai, notifications
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('permission_required', 255)->nullable();
            
            // Standard timestamps
            $table->timestamps();

            // Tenant Isolation relationship
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            // Quick lookup indexes
            $table->index(['searchable_type', 'searchable_id']);
            $table->index(['module']);
            $table->index(['permission_required']);
            $table->index(['created_at']);
        });

        // Conditionally setup pgvector column
        if ($hasPgVector) {
            try {
                // OpenAI / Gemini standard embeddings are typically 1536 or 768 dimensions
                DB::statement('ALTER TABLE search_indexes ADD COLUMN embedding vector(1536)');
                DB::statement('CREATE INDEX search_indexes_embedding_cosine_idx ON search_indexes USING hnsw (embedding vector_cosine_ops)');
            } catch (\Throwable $e) {
                // If adding vector fails, create fallback JSONB column
                Schema::table('search_indexes', function (Blueprint $table) {
                    $table->jsonb('embedding')->nullable();
                });
            }
        } else {
            Schema::table('search_indexes', function (Blueprint $table) {
                $table->jsonb('embedding')->nullable();
            });
        }

        // Add Full Text Search tsvector and triggers
        try {
            DB::statement('ALTER TABLE search_indexes ADD COLUMN searchable_text tsvector');

            DB::statement("
                CREATE FUNCTION search_indexes_tsvector_trigger() RETURNS trigger AS $$
                begin
                    new.searchable_text :=
                        setweight(to_tsvector('english', coalesce(new.title,'')), 'A') ||
                        setweight(to_tsvector('english', coalesce(new.description,'')), 'B') ||
                        setweight(to_tsvector('english', coalesce(new.content,'')), 'C');
                    return new;
                end;
                $$ LANGUAGE plpgsql
            ");

            DB::statement("
                CREATE TRIGGER tsvectorupdate BEFORE INSERT OR UPDATE
                ON search_indexes FOR EACH ROW EXECUTE FUNCTION search_indexes_tsvector_trigger()
            ");

            // Create GIN index for search_text
            DB::statement('CREATE INDEX search_indexes_searchable_text_gin ON search_indexes USING gin(searchable_text)');
        } catch (\Throwable $e) {
            // Fallback for non-postgres or legacy postgres configurations
        }

        // Create pg_trgm trigram index for fast autocomplete
        try {
            DB::statement('CREATE INDEX search_indexes_title_trgm ON search_indexes USING gin(title gin_trgm_ops)');
        } catch (\Throwable $e) {
            // Fallback if pg_trgm is not enabled/allowed
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_indexes');
        
        try {
            DB::statement('DROP TRIGGER IF EXISTS tsvectorupdate ON search_indexes');
            DB::statement('DROP FUNCTION IF EXISTS search_indexes_tsvector_trigger()');
        } catch (\Throwable $e) {}
    }
};
