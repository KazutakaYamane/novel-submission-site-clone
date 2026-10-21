<?php

return [
    // カクヨムのヘルプを確認できなかったため仮の値
    'max_length' => [
        'title' => 100,
        'catchphrase' => 35,
        'introduction' => 2000,
    ],
    // カクヨムのヘルプ「小説の設定を変更する」の上限に合わせる
    'tags' => [
        'max_count' => 8,
        'max_length' => 20,
    ],
];
