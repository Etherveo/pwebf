<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// M2 CAT-01..04, PRD-01..04. Category/product write logic lives here,
// not in controllers: controllers only validate (FormRequest) and call
// one service method, then redirect.
class CatalogService
{
    public function __construct(private readonly StockService $stock) {}

    public function createCategory(array $data): Category
    {
        $name = trim((string) $data['name']);

        return Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
        ]);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $name = trim((string) $data['name']);

        $category->update([
            'name' => $name,
            'slug' => $name !== $category->name
                ? $this->uniqueSlug($name, $category->id)
                : $category->slug,
        ]);

        return $category->fresh();
    }

    public function createProduct(array $data, User $actor): Product
    {
        return DB::transaction(function () use ($data, $actor) {
            $isPreorder = (bool) ($data['is_preorder'] ?? false);
            $condition = (string) ($data['condition'] ?? 'baru');

            $product = Product::create([
                'category_id' => $data['category_id'],
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'price_idr' => (int) $data['price_idr'],
                'condition' => in_array($condition, Product::CONDITIONS, true)
                    ? $condition
                    : 'baru',
                'is_preorder' => $isPreorder,
                'preorder_eta' => $isPreorder && isset($data['preorder_eta']) && trim((string) $data['preorder_eta']) !== ''
                    ? trim((string) $data['preorder_eta'])
                    : null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'image_path' => $data['image_path'] ?? null,
                'stock_on_hand' => (int) $data['stock_on_hand'],
                'stock_reserved' => 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->stock->recordInitialStock($product, $actor);

            return $product;
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $isPreorder = array_key_exists('is_preorder', $data)
            ? (bool) $data['is_preorder']
            : (bool) $product->is_preorder;

        $imagePath = $product->image_path;
        if (! empty($data['remove_image']) && $imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
            Storage::disk('local')->delete($imagePath);
            $imagePath = null;
        }
        if (array_key_exists('image_path', $data) && $data['image_path'] !== null) {
            if ($product->image_path !== null && $product->image_path !== $data['image_path']) {
                Storage::disk('public')->delete($product->image_path);
                Storage::disk('local')->delete($product->image_path);
            }
            $imagePath = $data['image_path'];
        }

        $preorderEta = array_key_exists('preorder_eta', $data)
            ? (trim((string) $data['preorder_eta']) !== '' ? trim((string) $data['preorder_eta']) : null)
            : $product->preorder_eta;

        $product->update([
            'category_id' => $data['category_id'] ?? $product->category_id,
            'name' => isset($data['name']) ? trim((string) $data['name']) : $product->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'price_idr' => isset($data['price_idr']) ? (int) $data['price_idr'] : $product->price_idr,
            'condition' => isset($data['condition']) && in_array($data['condition'], Product::CONDITIONS, true)
                ? $data['condition']
                : ($product->condition ?? 'baru'),
            'is_preorder' => $isPreorder,
            'preorder_eta' => $isPreorder ? $preorderEta : null,
            'is_featured' => array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : (bool) $product->is_featured,
            'image_path' => $imagePath,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $product->is_active,
        ]);

        return $product->fresh();
    }

    public function toggleProduct(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        return $product->fresh();
    }

    private function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'kategori';
        }

        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
