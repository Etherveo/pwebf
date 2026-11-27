<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 50);
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('payment_method', 50);
            $table->unsignedBigInteger('amount_idr');
            $table->string('currency', 10)->default('IDR');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled']);
            $table->string('reference_code')->unique();
            $table->string('payment_token')->unique();
            $table->string('payment_url', 2048)->nullable();
            $table->text('qr_string')->nullable();
            $table->string('snap_token')->nullable();
            $table->string('qr_url', 2048)->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'paid_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Append-only audit trail transisi status pembayaran.
        Schema::create('payment_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->enum('to_status', ['pending', 'paid', 'failed', 'expired', 'cancelled']);
            $table->string('trigger');
            $table->string('request_id')->nullable()->index();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['payment_id', 'occurred_at']);
        });

        // Log audit webhook payment gateway riil (tanpa atribut simulasi).
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('external_event_id')->nullable()->index();
            $table->boolean('signature_valid')->default(false);
            $table->unsignedSmallInteger('http_status');
            $table->json('payload_sanitized_json')->nullable();
            $table->timestamp('received_at');

            $table->index(['provider', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payment_status_transitions');
        Schema::dropIfExists('payments');
    }
};
