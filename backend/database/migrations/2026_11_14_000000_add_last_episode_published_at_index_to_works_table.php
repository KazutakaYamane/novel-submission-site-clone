<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('works', function (Blueprint $table) {
            // 新着の一覧は日時の降順・id の昇順で並べる。向きを ORDER BY と揃え、filesort せずに先頭から LIMIT 件だけ読む
            $table->rawIndex('last_episode_published_at desc, id', 'works_last_episode_published_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table) {
            $table->dropIndex('works_last_episode_published_at_id_index');
        });
    }
};
