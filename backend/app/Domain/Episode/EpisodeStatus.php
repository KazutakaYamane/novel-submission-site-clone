<?php

namespace App\Domain\Episode;

enum EpisodeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
