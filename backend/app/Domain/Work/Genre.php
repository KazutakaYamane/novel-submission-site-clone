<?php

namespace App\Domain\Work;

// 値はカクヨムのジャンルの URL のスラッグに合わせる
enum Genre: string
{
    case Fantasy = 'fantasy';
    case Action = 'action';
    case Sf = 'sf';
    case LoveStory = 'love_story';
    case Romance = 'romance';
    case Drama = 'drama';
    case Horror = 'horror';
    case Mystery = 'mystery';
    case Nonfiction = 'nonfiction';
    case History = 'history';
    case Criticism = 'criticism';
    case Others = 'others';
    case Maho = 'maho';
    case FanFiction = 'fan_fiction';
}
