<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Provisioning admin_toko via artisan CLI only (no public admin registration).
test('ACC-05 provision creates admin_toko account with hashed password', function () {
    $this->artisan('store:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin@contoh.test',
        'name' => 'Admin Satu',
        '--password' => 'rahasia123',
    ])->assertExitCode(0);

    $admin = User::where('email', 'admin@contoh.test')->first();
    expect($admin)->not->toBeNull();
    expect($admin->role)->toBe('admin_toko');
    expect($admin->is_active)->toBeTrue();
    expect(Hash::check('rahasia123', $admin->password))->toBeTrue();
});

test('ACC-05 re-running without force fails and does not overwrite the password', function () {
    $this->artisan('store:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin@contoh.test',
        'name' => 'Admin Satu',
        '--password' => 'rahasia123',
    ])->assertExitCode(0);

    $this->artisan('store:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin@contoh.test',
        'name' => 'Admin Satu',
        '--password' => 'lainnya123',
    ])->assertExitCode(1);

    expect(Hash::check('rahasia123', User::first()->password))->toBeTrue();
    expect(Hash::check('lainnya123', User::first()->password))->toBeFalse();
});

test('ACC-05 provisioning refuses a buyer email, non-admin roles, and short passwords', function () {
    User::factory()->create(['email' => 'nara@contoh.test']);

    $this->artisan('store:provision-account', [
        'role' => 'admin_toko',
        'email' => 'nara@contoh.test',
        'name' => 'Bukan Admin',
        '--password' => 'rahasia123',
    ])->assertExitCode(1);
    expect(User::where('email', 'nara@contoh.test')->first()->role)->toBe('pembeli');

    $this->artisan('store:provision-account', [
        'role' => 'developer',
        'email' => 'dev@contoh.test',
        'name' => 'Dev',
        '--password' => 'rahasia123',
    ])->assertExitCode(1);

    $this->artisan('store:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin2@contoh.test',
        'name' => 'Admin Pendek',
        '--password' => 'pendek',
    ])->assertExitCode(1);
    expect(User::where('email', 'admin2@contoh.test')->exists())->toBeFalse();
});
