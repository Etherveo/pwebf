<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('condition', 20)->default('baru')->after('price_idr');
            $table->boolean('is_preorder')->default(false)->after('condition');
            $table->string('preorder_eta', 150)->nullable()->after('is_preorder');
            $table->boolean('is_featured')->default(false)->after('preorder_eta');
            $table->string('image_path')->nullable()->after('is_featured');

            $table->index(['is_active', 'condition']);
            $table->index(['is_active', 'is_preorder']);
            $table->index(['is_active', 'is_featured']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_method', 20)->default('pickup')->after('status');
            $table->unsignedBigInteger('subtotal_idr')->nullable()->after('fulfillment_method');
            $table->unsignedBigInteger('shipping_cost_idr')->default(0)->after('subtotal_idr');
            $table->string('courier_code', 30)->nullable()->after('shipping_cost_idr');
            $table->string('courier_name', 80)->nullable()->after('courier_code');
            $table->string('shipping_recipient_name', 150)->nullable()->after('courier_name');
            $table->string('shipping_phone', 40)->nullable()->after('shipping_recipient_name');
            $table->text('shipping_address_snapshot')->nullable()->after('shipping_phone');
            $table->string('tracking_number', 100)->nullable()->after('shipping_address_snapshot');
            $table->text('buyer_note')->nullable()->after('tracking_number');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('qr_url');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['proof_path', 'proof_uploaded_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_method',
                'subtotal_idr',
                'shipping_cost_idr',
                'courier_code',
                'courier_name',
                'shipping_recipient_name',
                'shipping_phone',
                'shipping_address_snapshot',
                'tracking_number',
                'buyer_note',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'condition']);
            $table->dropIndex(['is_active', 'is_preorder']);
            $table->dropIndex(['is_active', 'is_featured']);
            $table->dropColumn([
                'condition',
                'is_preorder',
                'preorder_eta',
                'is_featured',
                'image_path',
            ]);
        });
    }
};
