<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_idr');
            $table->string('condition', 20)->default('baru');
            $table->boolean('is_preorder')->default(false);
            $table->string('preorder_eta', 150)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('image_path')->nullable();
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('stock_reserved')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['is_active', 'condition']);
            $table->index(['is_active', 'is_preorder']);
            $table->index(['is_active', 'is_featured']);
        });

        // Append-only ledger riwayat perubahan stok.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->enum('type', ['penyesuaian', 'cadangkan', 'konfirmasi', 'lepas']);
            $table->integer('delta_on_hand')->default(0);
            $table->integer('delta_reserved')->default(0);
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
