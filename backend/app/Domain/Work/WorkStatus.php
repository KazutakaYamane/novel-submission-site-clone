<?php

namespace App\Domain\Work;

enum WorkStatus: string
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
}
