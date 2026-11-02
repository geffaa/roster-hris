<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Searchable passages. 'simple' text search works for both English and Bahasa Indonesia without stemming surprises.
        Schema::create('policy_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('heading')->nullable();
            $table->text('text');
            $table->timestamps();
            $table->index(['company_id', 'policy_document_id']);
        });
        DB::statement("ALTER TABLE policy_chunks ADD COLUMN tsv tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(heading, '') || ' ' || text)) STORED");
        DB::statement('CREATE INDEX policy_chunks_tsv ON policy_chunks USING GIN (tsv)');

        // Every question and answer, for the person's history and for audit.
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('question'); // question | payroll_summary
            $table->text('question');
            $table->jsonb('answer');
            $table->string('mode'); // claude | extractive | refused
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('policy_chunks');
        Schema::dropIfExists('policy_documents');
    }
};
