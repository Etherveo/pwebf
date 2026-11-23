<?php

use App\Models\User;

test('default store brand renders Jubarol and omits Ira Stationery, Gorontalo, and Developer UI', function () {
    $pages = ['/', '/katalog', '/masuk', '/daftar'];

    foreach ($pages as $uri) {
        $this->get($uri)->assertOk()
            ->assertSee('Jubarol')
            ->assertDontSee('Ira Stationery')
            ->assertDontSee('Toko Ira')
            ->assertDontSee('Gorontalo')
            ->assertDontSee('Developer')
            ->assertDontSee('SIMULASI');
    }

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $this->get('/admin')->assertOk()
        ->assertSee('Jubarol')
        ->assertDontSee('Ira Stationery')
        ->assertDontSee('Gorontalo')
        ->assertDontSee('panel Developer')
        ->assertDontSee('has-developer-sidebar');
});

test('store brand name and copy switch cleanly from centralized config', function () {
    config([
        'store.name' => 'Kios Aksara',
        'store.mark' => 'K',
        'store.location' => 'Makassar',
        'store.tagline' => 'Ringkas setiap hari.',
    ]);

    $this->get('/')->assertOk()
        ->assertSee('<title>Kios Aksara</title>', false)
        ->assertSee('Kios Aksara')
        ->assertSee('Makassar')
        ->assertDontSee('Jubarol')
        ->assertDontSee('Ira Stationery');

    $this->get('/masuk')->assertOk()
        ->assertSee('Kios Aksara')
        ->assertSee('Ringkas setiap hari.')
        ->assertDontSee('Jubarol');
});

test('buyer views render minimalist shell without simulation or trace elements', function () {
    $buyer = User::factory()->create();
    $this->actingAs($buyer, 'web');

    $this->get('/keranjang')->assertOk()
        ->assertSee('Jubarol')
        ->assertDontSee('Toko Ira')
        ->assertDontSee('SIMULASI');

    $this->get('/profil')->assertOk()
        ->assertSee('Jubarol')
        ->assertDontSee('Ira Stationery');
});

test('vite minimalist build manifest exists with compiled assets', function () {
    $manifestPath = public_path('build/manifest.json');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray()
        ->toHaveKey('resources/css/app.css')
        ->toHaveKey('resources/js/app.js');
});
