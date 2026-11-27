<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['pembeli', 'admin_toko'])->default('pembeli')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->string('deactivation_reason', 255)->nullable()->after('deactivated_at');
            $table->string('username')->nullable()->unique()->after('deactivation_reason');
            $table->string('phone')->nullable()->after('username');
            $table->enum('gender', ['laki-laki', 'perempuan'])->nullable()->after('phone');
            $table->date('birthdate')->nullable()->after('gender');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropIndex(['role']);
            $table->dropColumn([
                'role',
                'is_active',
                'deactivated_at',
                'deactivation_reason',
                'username',
                'phone',
                'gender',
                'birthdate',
            ]);
        });
    }
};
