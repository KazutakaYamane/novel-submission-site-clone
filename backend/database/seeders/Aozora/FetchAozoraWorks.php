<?php

namespace Database\Seeders\Aozora;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

// 日次リセットのたびに青空文庫へアクセスしないよう、開発時に 1 回だけ実行して出力をコミットする
final class FetchAozoraWorks extends Command
{
    protected $signature = 'seed-data:aozora
        {--only= : この slug の作品だけを取得する}
        {--output= : JSON の書き先のディレクトリ(既定は database/seeders/data/works)}';

    protected $description = '青空文庫からシードの作品を取得し、作品ごとの JSON にする';

    public function handle(): int
    {
        /** @var list<array{slug: string, card_url: string, genre: string, status: string, catchphrase: string, introduction: string, tags: list<string>, title?: string, author?: string}> $manifest */
        $manifest = json_decode(File::get(database_path('seeders/data/aozora-manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $only = $this->option('only');
        if (is_string($only)) {
            $manifest = array_values(array_filter($manifest, fn (array $work): bool => $work['slug'] === $only));
            if ($manifest === []) {
                $this->error("マニフェストに {$only} がありません");

                return self::FAILURE;
            }
        }

        $output = $this->option('output');
        $outputDir = is_string($output) ? $output : database_path('seeders/data/works');
        File::ensureDirectoryExists($outputDir);

        foreach ($manifest as $work) {
            $zipUrl = $this->rubyZipUrl($work['card_url']);
            if ($zipUrl === null) {
                $this->error("{$work['slug']}: 図書カードに「ルビあり」の zip がありません");

                return self::FAILURE;
            }

            $document = AozoraText::parse(mb_convert_encoding(self::readZippedText($this->get($zipUrl)), 'UTF-8', 'SJIS-win'));
            $episodes = $document->episodes;
            $length = array_sum(array_map(fn (array $episode): int => mb_strlen($episode['body']), $episodes));
            // クレジットは専用のカラムを持たず、本文に含める(mvp.md §13)
            $last = array_key_last($episodes);
            $episodes[$last]['body'] .= "\n\n".$document->credit;

            File::put("{$outputDir}/{$work['slug']}.json", json_encode([
                'slug' => $work['slug'],
                // ヘッダーは副題や訳者の行の有無で行の意味が変わり、作品名・著者名を見分けられない。
                // そうした作品はマニフェストに作品名・著者名を書き、ヘッダーより優先する
                'author' => $work['author'] ?? $document->author,
                'title' => $work['title'] ?? $document->title,
                'catchphrase' => $work['catchphrase'],
                'introduction' => $work['introduction'],
                'genre' => $work['genre'],
                'status' => $work['status'],
                'tags' => $work['tags'],
                'source' => ['card_url' => $work['card_url'], 'zip_url' => $zipUrl],
                'episodes' => $episodes,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

            $this->line(sprintf('%s: %d話 %d文字 〓%d件', $work['slug'], count($episodes), $length, $document->missingGlyphCount));
        }

        return self::SUCCESS;
    }

    // 図書カードの zip へのリンクは図書カードからの相対パス(./files/127_ruby_150.zip)で書かれている
    private function rubyZipUrl(string $cardUrl): ?string
    {
        if (preg_match('/href="(?:\.\/)?([^"]*_ruby_[^"]*\.zip)"/', $this->get($cardUrl), $m) !== 1) {
            return null;
        }

        return dirname($cardUrl).'/'.$m[1];
    }

    private function get(string $url): string
    {
        return Http::timeout(30)->get($url)->throw()->body();
    }

    // ZipArchive はファイルしか開けないので、一時ファイルに書いてから読む
    private static function readZippedText(string $zipBytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aozora');
        if ($path === false) {
            throw new RuntimeException('一時ファイルを作れません');
        }
        try {
            File::put($path, $zipBytes);
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('zip を開けません');
            }
            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name !== false && str_ends_with($name, '.txt')) {
                        return (string) $zip->getFromIndex($i);
                    }
                }
            } finally {
                $zip->close();
            }
            throw new RuntimeException('zip に .txt がありません');
        } finally {
            File::delete($path);
        }
    }
}
