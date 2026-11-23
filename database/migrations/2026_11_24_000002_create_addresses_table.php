<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->string('province');
            $table->string('city');
            $table->string('district');
            $table->string('village');
            $table->string('postal_code', 10);
            $table->text('street_address');
            $table->enum('address_type', ['Rumah', 'Kantor', 'Lainnya'])->default('Rumah');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
