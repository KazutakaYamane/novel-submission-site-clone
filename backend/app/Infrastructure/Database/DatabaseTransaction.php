<?php

namespace App\Infrastructure\Database;

use App\Domain\Shared\Transaction;
use Illuminate\Support\Facades\DB;

final class DatabaseTransaction implements Transaction
{
    public function run(callable $callback): mixed
    {
        return DB::transaction(fn () => $callback());
    }
}
