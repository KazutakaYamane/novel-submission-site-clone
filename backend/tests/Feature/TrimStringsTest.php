<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 話の本文だけは trim しないが、その除外がほかの API の同じ名前の項目に広がらないようにする
it('話の API 以外では body も前後の空白を削る', function () {
    Route::post('/api/test-trim', fn (Request $request) => ['body' => $request->input('body')]);

    $this->postJson('/api/test-trim', ['body' => "　本文\n"])
        ->assertOk()
        ->assertExactJson(['body' => '本文']);
});
