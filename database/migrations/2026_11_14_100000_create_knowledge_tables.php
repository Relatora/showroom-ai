<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the assistant's knowledge: uploaded documents, and the searchable chunks cut
 * from them (and from vehicles, lead notes and timeline entries). See ADR-002 and ADR-003.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // policy | warranty | finance | spec_sheet | process | other
            $table->string('category')->index();
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            // pending -> extracting -> embedding -> ready, or failed
            $table->string('status')->default('pending')->index();
            $table->text('error')->nullable();
            $table->unsignedInteger('chunks_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            // Where the text came from: a Document, Vehicle, Lead or Activity.
            $table->morphs('source');
            // For leads and activities: the lead it belongs to. Retrieval filters on this to
            // apply the same visibility rules as the rest of the app. Null for shared knowledge.
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('heading')->nullable();
            $table->text('content');
            $table->unsignedInteger('token_estimate');
            // Hash of the text that was embedded, so re-indexing unchanged text is free.
            $table->string('content_hash', 64);
            $table->vector('embedding', dimensions: 768)->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id', 'position']);
        });

        // Cosine HNSW index for the vector half of hybrid search.
        Schema::table('chunks', function (Blueprint $table) {
            $table->vectorIndex('embedding');
        });

        // Keyword half: a generated tsvector, so it can never drift out of sync with the text.
        // Headings get a higher weight, since a match there is usually what the person meant.
        DB::statement(<<<'SQL'
            ALTER TABLE chunks ADD COLUMN search tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(heading, '')), 'A') ||
                setweight(to_tsvector('english', content), 'B')
            ) STORED
        SQL);
        DB::statement('CREATE INDEX chunks_search_index ON chunks USING gin (search)');
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
        Schema::dropIfExists('documents');
    }
};
