<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // 一覧の作品ごとの公開済みの話数を、行(本文を含む)を読まずに索引だけで数える
            $table->index(['work_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex(['work_id', 'status']);
        });
    }
};
