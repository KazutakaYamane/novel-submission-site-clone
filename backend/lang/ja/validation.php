<?php

// API の 422 をフロントがそのまま表示するため、使っている規則の分だけ訳す。
// :attribute の前後に空白を入れない(「この メールアドレス」にならないように)
return [
    'distinct' => ':attributeに同じ値が含まれています',
    'email' => ':attributeの形式が正しくありません',
    'enum' => ':attributeの値が正しくありません',
    'list' => ':attributeは配列で入力してください',
    'max' => [
        'array' => ':attributeは:max個以内で指定してください',
        'string' => ':attributeは:max文字以内で入力してください',
    ],
    'max_bytes' => ':attributeは:maxバイト以内で入力してください',
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください',
    ],
    'required' => ':attributeを入力してください',
    'string' => ':attributeは文字列で入力してください',
    'unique' => 'この:attributeは既に使われています',

    'attributes' => [
        'body' => '本文',
        'catchphrase' => 'キャッチコピー',
        'email' => 'メールアドレス',
        'genre' => 'ジャンル',
        'introduction' => 'あらすじ',
        'name' => '名前',
        'password' => 'パスワード',
        'rating_flags' => 'セルフレイティング',
        'rating_flags.*' => 'セルフレイティング',
        'status' => '連載状況',
        'tags' => 'タグ',
        'tags.*' => 'タグ',
        'title' => 'タイトル',
    ],
];
