<?php

namespace Tests\Doubles;

use App\Domain\Shared\TransactionInterface;
use Throwable;

final class InMemoryTransaction implements TransactionInterface
{
    /** @param  list<RollbackableDouble>  $doubles */
    public function __construct(private array $doubles) {}

    public function run(callable $callback): mixed
    {
        // 本物と同じく、例外で抜けたら書き込みを取り消す
        $snapshots = array_map(fn (RollbackableDouble $double) => $double->snapshot(), $this->doubles);
        try {
            return $callback();
        } catch (Throwable $e) {
            foreach ($this->doubles as $i => $double) {
                $double->restore($snapshots[$i]);
            }
            throw $e;
        }
    }
}
