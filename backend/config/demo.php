<?php

return [
    // IP ごとに 1 時間でデモを始められる回数。デモユーザーの量産と、それによる★の操作を抑える
    'rate_limit_per_hour' => (int) env('DEMO_RATE_LIMIT_PER_HOUR', 10),
];
