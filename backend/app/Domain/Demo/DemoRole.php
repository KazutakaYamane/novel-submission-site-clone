<?php

namespace App\Domain\Demo;

enum DemoRole: string
{
    case Author = 'author';
    case Reader = 'reader';
}
