<?php

it('DB と Redis の疎通結果を JSON で返す', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'checks' => ['database' => ['ok'], 'redis' => ['ok']], 'client_ip']);
});
