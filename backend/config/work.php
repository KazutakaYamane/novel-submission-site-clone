<?php

return [
    // カクヨムで確かめた上限
    'max_length' => [
        'title' => 100,
        'catchphrase' => 35,
        'introduction' => 10000,
    ],
    // カクヨムのヘルプ「小説の設定を変更する」の上限に合わせる
    'tags' => [
        'max_count' => 8,
        'max_length' => 20,
    ],
    // カクヨムで確かめた上限
    'episode_max_length' => [
        'title' => 100,
    ],
    // カクヨムの投稿画面で確かめた上限。ASCII と全角で境界の文字数が変わるので、文字数ではなくバイト数で数える
    'episode_body_max_bytes' => 6990474,
];
