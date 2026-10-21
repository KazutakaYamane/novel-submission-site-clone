<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // 既定の utf8mb4_unicode_ci は「パパ」と「ハハ」や別々の絵文字も同じ値とみなすので、
            // 同じタグの判定はアプリが小文字にした値の完全一致で行う
            $table->string('normalized_name')->collation('utf8mb4_bin')->unique();
        });

        Schema::create('work_tags', function (Blueprint $table) {
            $table->foreignUuid('work_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['work_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_tags');
        Schema::dropIfExists('tags');
    }
};
