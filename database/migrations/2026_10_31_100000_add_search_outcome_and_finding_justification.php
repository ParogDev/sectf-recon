<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->string('outcome', 16)->default('completed')->after('result_count');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->text('answers_flag')->nullable()->after('detail');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn('answers_flag');
        });

        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
