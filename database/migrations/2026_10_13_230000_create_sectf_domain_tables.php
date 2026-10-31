<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per contest company. Each contestant owns theirs.
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('url')->nullable();
            $table->json('domains')->nullable();
            $table->string('registry_id')->nullable(); // NEQ / corporation number
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Targets shared with another user (viewer = read, editor = write).
        Schema::create('target_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('viewer');
            $table->timestamps();

            $table->unique(['target_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_target_id')->nullable()->after('remember_token')
                ->constrained('targets')->nullOnDelete();
        });

        // The organiser's flag catalogue, shared by every target.
        Schema::create('flags', function (Blueprint $table) {
            $table->string('id', 8)->primary(); // "04-13"
            $table->string('category_id', 4);
            $table->string('category_en');
            $table->string('category_fr');
            $table->unsignedSmallInteger('points');
            $table->text('question_en');
            $table->text('question_fr');
            $table->boolean('bonus')->default(false);
            $table->boolean('call_only')->default(false);
            $table->string('related_flag_id', 8)->nullable(); // duplicate/overlap noted in the catalogue
            $table->unsignedSmallInteger('sort');
        });

        // Per-target progress on each flag.
        Schema::create('target_flag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->string('flag_id', 8);
            $table->foreign('flag_id')->references('id')->on('flags')->cascadeOnDelete();
            $table->string('status', 16)->default('open'); // open, partial, found, unverifiable
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_id', 'flag_id']);
        });

        // Where information came from. Phase 1 evidence = URL + when it was seen.
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->char('url_hash', 64); // sha256(url) for the per-target unique index
            $table->string('title')->nullable();
            $table->timestamp('retrieved_at');
            $table->date('content_date')->nullable();
            $table->string('content_date_basis')->nullable(); // "article byline", "post timestamp", "Last-Modified"...
            $table->string('screenshot_path')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_id', 'url_hash']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->string('flag_id', 8)->nullable();
            $table->foreign('flag_id')->references('id')->on('flags')->nullOnDelete();
            $table->string('key', 191);
            $table->text('claim');
            $table->text('detail')->nullable();
            $table->decimal('confidence', 3, 2)->default(0.5);
            $table->date('content_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_id', 'key']);
            $table->index(['target_id', 'flag_id']);
        });

        Schema::create('finding_source', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->text('quote')->nullable(); // the excerpt that supports the claim

            $table->unique(['finding_id', 'source_id']);
        });

        // Job-relevant facts only. No personal addresses, family, personal accounts.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('department')->nullable();
            $table->string('tenure')->nullable(); // "since 2019", "4 yrs" (flag 02-01)
            $table->text('profile_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_id', 'key']);
        });

        // Every number to be dialled on stage must be in the dossier.
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32); // normalised digits with leading +
            $table->string('extension', 16)->default('');
            $table->string('label');
            $table->string('department')->nullable();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('ring_verified_at')->nullable(); // rules allow dialling only to check it rings
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_id', 'number', 'extension']);
        });

        Schema::create('pretexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->string('title');
            $table->text('persona');
            $table->text('script')->nullable();
            $table->json('flag_ids')->nullable();
            $table->json('phone_number_ids')->nullable();
            $table->decimal('confidence', 3, 2)->default(0.5);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_id', 'key']);
        });

        // What has been searched, so two people (and their Claudes) don't repeat it.
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 64); // google, linkedin, dns, careers, fetch...
            $table->text('query');
            $table->string('flag_id', 8)->nullable();
            $table->integer('result_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_id', 'created_at']);
        });

        // Append-only feed: what get_updates returns and Reverb broadcasts.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32); // finding.recorded, flag.status, phone.added...
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary');
            $table->string('via', 16)->default('mcp'); // mcp, web, cli
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_target_id');
        });

        foreach (['activities', 'search_logs', 'pretexts', 'phone_numbers', 'people', 'finding_source',
            'findings', 'sources', 'target_flag', 'flags', 'target_user', 'targets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
