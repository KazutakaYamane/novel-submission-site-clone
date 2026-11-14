<?php

namespace Database\Seeders\Aozora;

final readonly class AozoraDocument
{
    /**
     * @param  list<array{title: string, body: string}>  $episodes
     */
    public function __construct(
        public string $title,
        public string $author,
        public array $episodes,
        public string $credit,
        // 作品名・著者名・本文・credit にある〓の数。Unicode が分からず〓にした外字と、元のテキストにある〓(判読できない文字)を合わせて数える
        public int $missingGlyphCount,
    ) {}
}
