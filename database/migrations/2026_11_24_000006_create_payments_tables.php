<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('provider_payment_id')->nullable();
            $table->string('payment_method');
            $table->unsignedBigInteger('amount_idr');
            $table->string('currency', 10)->default('IDR');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled']);
            $table->string('reference_code')->unique();
            $table->string('payment_token')->unique();
            $table->string('qr_url', 2048)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('order_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS payments_order_pending_unique ON payments (order_id) WHERE status = 'pending'");
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS payments_order_paid_unique ON payments (order_id) WHERE status = 'paid'");
        }

        // Append-only audit trail transisi status pembayaran.
        Schema::create('payment_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->enum('to_status', ['pending', 'paid', 'failed', 'expired', 'cancelled']);
            $table->string('trigger');
            $table->string('request_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['payment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_transitions');
        Schema::dropIfExists('payments');
    }
};
