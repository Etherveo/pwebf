<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['pembeli', 'admin_toko'])->default('pembeli');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 255)->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->enum('gender', ['laki-laki', 'perempuan'])->nullable();
            $table->date('birthdate')->nullable();
            $table->index('role');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (lower(email))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropIndex(['role']);
            $table->dropColumn([
                'role', 'is_active', 'deactivated_at', 'deactivation_reason',
                'username', 'phone', 'gender', 'birthdate',
            ]);
        });
    }
};
