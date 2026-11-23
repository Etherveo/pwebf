<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->enum('event_type', ['view', 'add_to_cart', 'booking', 'purchase', 'cancel']);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('session_ref')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['product_id', 'occurred_at']);
            $table->index(['user_id', 'product_id', 'occurred_at']);
            $table->index('order_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS events_order_item_lifecycle_unique ON events (order_item_id, event_type) WHERE order_item_id IS NOT NULL AND event_type IN ('booking', 'purchase', 'cancel')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
