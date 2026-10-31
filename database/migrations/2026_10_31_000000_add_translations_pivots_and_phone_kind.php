<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Narrative text is filed in English; a French rendering can sit next to it in
     * `translations` ({"fr": {...}}) so a French dossier is French end to end.
     * Pretexts also record the flags a call can pivot to, and phones get a kind
     * so the call sheet can show how varied the numbers are.
     */
    public function up(): void
    {
        foreach (['targets', 'findings', 'people', 'phone_numbers', 'pretexts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('translations')->nullable();
            });
        }

        Schema::table('pretexts', function (Blueprint $t) {
            $t->json('pivots')->nullable();
        });

        Schema::table('phone_numbers', function (Blueprint $t) {
            $t->string('kind', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('phone_numbers', fn (Blueprint $t) => $t->dropColumn(['translations', 'kind']));
        Schema::table('pretexts', fn (Blueprint $t) => $t->dropColumn(['translations', 'pivots']));

        foreach (['targets', 'findings', 'people'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('translations'));
        }
    }
};
