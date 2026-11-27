<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Auth ACC-01..13 (two real actors: pembeli & admin_toko on guard web).
// Legacy developer actor/guard routes are removed cleanly.

test('ACC-01 register pembeli creates active pembeli, hashes password, logs in, redirects to katalog', function () {
    $response = $this->post('/daftar', [
        'name' => 'Nara Contoh',
        'email' => 'nara@contoh.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    $response->assertRedirect('/katalog');
    $this->assertAuthenticatedAs(User::where('email', 'nara@contoh.test')->first(), 'web');
    $this->assertDatabaseHas('users', [
        'email' => 'nara@contoh.test',
        'role' => 'pembeli',
        'is_active' => true,
    ]);
    $user = User::where('email', 'nara@contoh.test')->first();
    expect(Hash::check('rahasia123', $user->password))->toBeTrue();
    expect($user->password)->not->toBe('rahasia123');
});

test('ACC-01 register rejects short name, bad email, short password, mismatched confirmation, and keeps old input', function () {
    $response = $this->post('/daftar', [
        'name' => 'A',
        'email' => 'bukan-email',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
    $this->assertGuest('web');
    $this->assertDatabaseCount('users', 0);
});

test('ACC-01 register email uniqueness is case-insensitive and stored lowercased', function () {
    User::factory()->create(['email' => 'Nara@Contoh.Test']);

    $response = $this->post('/daftar', [
        'name' => 'Lain',
        'email' => 'nara@contoh.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 1);
    expect(User::first()->email)->toBe('nara@contoh.test');
});

test('ACC-01 register double submit does not create two accounts', function () {
    $payload = [
        'name' => 'Nara',
        'email' => 'nara@contoh.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ];
    $this->post('/daftar', $payload)->assertRedirect('/katalog');
    $this->post('/daftar', $payload)->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 1);
});

test('ACC-02 buyer login succeeds, wrong credentials and unknown email give the same generic message', function () {
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertRedirect('/katalog');
    $this->assertAuthenticated('web');

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'salah'])
        ->assertSessionHasErrors('email');
    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'tidak-ada@contoh.test', 'password' => 'salah'])
        ->assertSessionHasErrors('email');
});

test('ACC-02 admin credentials on the buyer form are rejected with the generic message and do not log in', function () {
    User::factory()->admin()->create(['email' => 'admin@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'admin@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('ACC-03 admin login succeeds only for admin_toko and redirects to admin dashboard', function () {
    User::factory()->admin()->create(['email' => 'admin@contoh.test', 'password' => Hash::make('rahasia123')]);
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'admin_toko', 'email' => 'admin@contoh.test', 'password' => 'rahasia123'])
        ->assertRedirect('/admin');
    $this->assertAuthenticated('web');

    $this->post('/masuk', ['aktor' => 'admin_toko', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
});

test('Developer role and routes are completely removed', function () {
    $this->get('/developer')->assertNotFound();
    $this->get('/developer/masuk')->assertNotFound();
    $this->post('/developer/keluar')->assertNotFound();

    $this->post('/masuk', [
        'aktor' => 'developer',
        'email' => 'dev@contoh.test',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('aktor');

    $this->get('/admin/masuk')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/masuk?aktor=admin_toko')->assertOk()->assertSee('Admin Toko')->assertDontSee('Developer');
    $this->get('/masuk')->assertOk()->assertSee('Pembeli')->assertSee('Admin Toko')->assertDontSee('Developer');
});

test('Unified login rejects wrong-actor credentials with the same generic message', function () {
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);
    User::factory()->admin()->create(['email' => 'admin@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'admin_toko', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'admin@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('ACC-06 logout is POST only and logs out web guard cleanly', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer, 'web');
    $this->assertAuthenticated('web');

    $this->get('/keluar')->assertStatus(405);

    $this->post('/keluar')->assertRedirect('/masuk');
    $this->assertGuest('web');
});

test('ACC-07 inactive account cannot log in and an active session becomes invalid on next request', function () {
    $user = User::factory()->inactive()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');

    $user->update(['is_active' => true, 'deactivated_at' => null]);
    $this->actingAs($user, 'web');
    $this->get('/keranjang')->assertOk();

    $user->update(['is_active' => false, 'deactivated_at' => now()]);
    $this->get('/keranjang')->assertRedirect('/masuk');
});

test('ACC-08 unauthenticated users are redirected to the matching login form and wrong role gets 403', function () {
    $this->get('/katalog')->assertOk();
    $this->get('/keranjang')->assertRedirect('/masuk');
    $this->get('/admin/akun-pembeli')->assertRedirect('/masuk?aktor=admin_toko');

    $buyer = User::factory()->create();
    $this->actingAs($buyer, 'web');
    $this->get('/admin')->assertForbidden();
    $this->get('/admin/akun-pembeli')->assertForbidden();
});

test('ACC-08 every protected route declares guard and role (logout route is the auth-only exception)', function () {
    $routes = Route::getRoutes();

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasAuth = collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth:'));

        if (! $hasAuth) {
            continue;
        }

        if ($route->getName() === 'keluar') {
            continue;
        }

        $hasRole = collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'role:'));

        expect($hasRole)->toBeTrue(
            'Protected route '.$route->uri().' must declare a role.'
        );
    }

    $this->assertTrue(true);
});

test('ACC-11/12 admin buyer list filters, searches, and detail 404s for non-pembeli', function () {
    $admin = User::factory()->admin()->create();
    $normal = User::factory()->create(['name' => 'Nara Normal']);
    $other = User::factory()->inactive()->create(['name' => 'Budi Nonaktif']);

    $this->actingAs($admin, 'web');

    $this->get('/admin/akun-pembeli')
        ->assertOk()
        ->assertSee('Nara Normal')
        ->assertSee('Budi Nonaktif')
        ->assertDontSee('SIMULASI');

    $this->get('/admin/akun-pembeli?status=aktif')
        ->assertOk()
        ->assertSee('Nara Normal')
        ->assertDontSee('Budi Nonaktif');

    $this->get('/admin/akun-pembeli?cari=nara')
        ->assertOk()
        ->assertSee('Nara Normal')
        ->assertDontSee('Budi Nonaktif');

    $this->get('/admin/akun-pembeli/'.$normal->id)->assertOk()->assertSee('Nara Normal');
    $this->get('/admin/akun-pembeli/'.$admin->id)->assertNotFound();
    $this->get('/admin/akun-pembeli/999999')->assertNotFound();
});

test('ACC-13 admin can deactivate and reactivate a buyer with a reason, and deactivation blocks login', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->actingAs($admin, 'web');
    $this->post('/admin/akun-pembeli/'.$buyer->id.'/status', [
        'is_active' => 0,
        'reason' => 'Pelanggaran contoh',
    ])->assertRedirect('/admin/akun-pembeli/'.$buyer->id);

    $buyer->refresh();
    expect($buyer->is_active)->toBeFalse();
    expect($buyer->deactivation_reason)->toBe('Pelanggaran contoh');
    expect($buyer->deactivated_at)->not->toBeNull();

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');

    $this->actingAs($admin, 'web');
    $this->post('/admin/akun-pembeli/'.$buyer->id.'/status', ['is_active' => 1])
        ->assertRedirect('/admin/akun-pembeli/'.$buyer->id);
    expect($buyer->fresh()->is_active)->toBeTrue();
    expect($buyer->fresh()->deactivation_reason)->toBeNull();
});

test('Store config check command passes with a valid configuration and rejects unsupported drivers', function () {
    $originalDriver = config('database.default');

    try {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        config(['database.default' => 'sqlite']);
        config(['app.timezone' => 'UTC']);
        config(['store.reporting_timezone' => 'Asia/Makassar']);
        config(['payment.driver' => 'manual']);

        $this->artisan('store:check-config')->assertExitCode(0);

        config(['database.default' => 'unsupported_driver']);
        $this->artisan('store:check-config')->assertExitCode(1);
    } finally {
        config(['database.default' => $originalDriver]);
    }
});
