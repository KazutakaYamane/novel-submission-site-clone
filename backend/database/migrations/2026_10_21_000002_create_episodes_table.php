<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->unsignedInteger('position');
            // MySQL の ENUM 型は値を足すたびに ALTER が要るので、enum の列は string にする
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // 採番は作品の行のロックで直列にするが、ロックが漏れても同じ番号を DB が止める
            $table->unique(['work_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
