<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'normalized_name'])]
class Tag extends Model
{
    use HasUuids;

    public $timestamps = false;
}
