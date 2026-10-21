<?php

namespace App\Domain\Work;

enum RatingFlag: string
{
    case Cruelty = 'cruelty';
    case Violence = 'violence';
    case Sexual = 'sexual';
}
