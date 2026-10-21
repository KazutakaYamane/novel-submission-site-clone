<?php

// API の 422 をフロントがそのまま表示するため、使っている規則の分だけ訳す。
// :attribute の前後に空白を入れない(「この メールアドレス」にならないように)
return [
    'email' => ':attributeの形式が正しくありません',
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください',
    ],
    'required' => ':attributeを入力してください',
    'string' => ':attributeは文字列で入力してください',
    'unique' => 'この:attributeは既に使われています',

    'attributes' => [
        'email' => 'メールアドレス',
        'name' => '名前',
        'password' => 'パスワード',
    ],
];
