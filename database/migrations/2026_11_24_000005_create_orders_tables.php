<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', [
                'menunggu_pembayaran',
                'dibayar',
                'diproses',
                'selesai',
                'dibatalkan',
                'kedaluwarsa',
            ]);
            $table->string('fulfillment_method', 20)->default('pickup');
            $table->unsignedBigInteger('subtotal_idr')->nullable();
            $table->unsignedBigInteger('shipping_cost_idr')->default(0);
            $table->unsignedBigInteger('total_idr');
            $table->string('courier_code', 30)->nullable();
            $table->string('courier_name', 80)->nullable();
            $table->string('shipping_recipient_name', 150)->nullable();
            $table->string('shipping_phone', 40)->nullable();
            $table->text('shipping_address_snapshot')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->text('buyer_note')->nullable();
            $table->timestamp('payment_deadline_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->string('checkout_key');
            $table->timestamps();

            $table->unique(['user_id', 'checkout_key']);
            $table->index(['status', 'payment_deadline_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('paid_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->unsignedBigInteger('unit_price_idr');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal_idr');
            $table->timestamps();
        });

        // Append-only audit trail transisi status pesanan.
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->enum('to_status', [
                'menunggu_pembayaran',
                'dibayar',
                'diproses',
                'selesai',
                'dibatalkan',
                'kedaluwarsa',
            ]);
            $table->string('trigger');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
