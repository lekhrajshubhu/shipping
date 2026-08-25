<?php

it('exposes the shipping health route', function (): void {
    $this->getJson('/shipping/health')
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);
});
