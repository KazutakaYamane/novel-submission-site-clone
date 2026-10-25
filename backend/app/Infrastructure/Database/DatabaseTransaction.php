<?php

namespace App\Infrastructure\Database;

use App\Domain\Shared\TransactionInterface;
use Illuminate\Support\Facades\DB;

final class DatabaseTransaction implements TransactionInterface
{
    public function run(callable $callback): mixed
    {
        return DB::transaction(fn () => $callback());
    }
}
