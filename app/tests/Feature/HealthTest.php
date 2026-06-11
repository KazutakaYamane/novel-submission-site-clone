<?php

it('returns ok from the health endpoint', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'database' => 'ok',
        ]);
});
