<?php

namespace Tests\Doubles;

/**
 * 本物のトランザクションが取り消す状態(DB の行)を snapshot で返す。InMemoryTransaction が例外のときに restore で戻す
 */
interface RollbackableDouble
{
    public function snapshot(): mixed;

    public function restore(mixed $snapshot): void;
}
