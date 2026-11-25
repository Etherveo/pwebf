<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M2 PRD-01..07, STK-01, blueprint §6.2.
// stock_available is DERIVED (stock_on_hand - stock_reserved), never a
// stored column, so it cannot drift from the two stored values.
// The DB CHECK (stock_reserved <= stock_on_hand) guarantees it is >= 0.
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const CONDITIONS = ['baru', 'bekas'];

    public const CONDITION_LABELS = [
        'baru' => 'Baru',
        'bekas' => 'Bekas',
    ];

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price_idr',
        'condition',
        'is_preorder',
        'preorder_eta',
        'is_featured',
        'image_path',
        'stock_on_hand',
        'stock_reserved',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_idr' => 'integer',
            'is_preorder' => 'boolean',
            'is_featured' => 'boolean',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function stockAvailable(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, (int) $this->stock_on_hand - (int) $this->stock_reserved),
        );
    }

    public function formattedPrice(): string
    {
        return 'Rp '.number_format((int) $this->price_idr, 0, ',', '.');
    }

    public function conditionLabel(): string
    {
        return self::CONDITION_LABELS[$this->condition ?? 'baru'] ?? 'Baru';
    }

    public function hasImage(): bool
    {
        return is_string($this->image_path) && trim($this->image_path) !== '';
    }

    public function imageUrl(): ?string
    {
        return $this->hasImage() ? route('produk.foto', $this) : null;
    }
}
