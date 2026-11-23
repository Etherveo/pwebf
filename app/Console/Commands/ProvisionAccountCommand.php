<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

// Admin account provisioning CLI (there is deliberately no public admin registration).
class ProvisionAccountCommand extends Command
{
    protected $signature = 'store:provision-account
        {role : admin_toko}
        {email : account email}
        {name : account name}
        {--password= : password (if omitted, prompted interactively)}
        {--force : overwrite an existing admin account without confirmation}';

    protected $description = 'Provision an admin_toko account';

    public function handle(): int
    {
        $role = $this->argument('role');
        if ($role !== 'admin_toko') {
            $this->error('Role yang didukung hanya admin_toko.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Nama dan email yang valid wajib diisi.');

            return self::FAILURE;
        }

        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($existing instanceof User && $existing->role !== 'admin_toko') {
            $this->error('Email ini sudah dipakai akun pembeli. Provisioning tidak mengubah role pembeli menjadi admin.');

            return self::FAILURE;
        }

        if ($existing !== null && ! $this->option('force')) {
            $this->error('Akun dengan email ini sudah ada. Jalankan ulang dengan --force dan konfirmasi untuk menimpa.');

            return self::FAILURE;
        }

        if ($existing !== null && $this->option('force') && ! $this->confirm('Akun sudah ada. Timpa kata sandi akun ini?')) {
            $this->info('Dibatalkan. Akun tidak diubah.');

            return self::SUCCESS;
        }

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = $this->secret('Kata sandi (minimal 8 karakter)');
        }

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');

            return self::FAILURE;
        }

        $hashed = Hash::make($password);

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $hashed, 'role' => 'admin_toko', 'is_active' => true],
        );

        $this->info("Akun {$role} ({$email}) berhasil diprovisioning.");

        return self::SUCCESS;
    }
}
