<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Data katalog awal Cloraamine (Photocard, Album, Merchandise, Stiker Custom, Pin Button Custom).
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Photocard' => [
                [
                    'name' => 'Photocard Set Era Pertama (Isi 5 Kartu)',
                    'price_idr' => 25000,
                    'stock' => 20,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => true,
                    'description' => 'Set photocard koleksi edisi era pertama berisi 5 kartu cetak tebal berlaminasi.',
                ],
                [
                    'name' => 'Photocard Solo Edisi Latar Khusus',
                    'price_idr' => 15000,
                    'stock' => 12,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Photocard solo satu lembar dengan hasil cetak tajam dan sleeve pelindung.',
                ],
                [
                    'name' => 'Photocard Unit Edisi Kedua (Pre-Order)',
                    'price_idr' => 30000,
                    'stock' => 25,
                    'condition' => 'baru',
                    'is_preorder' => true,
                    'preorder_eta' => 'Estimasi 30–45 hari sejak pemesanan',
                    'is_featured' => true,
                    'description' => 'Photocard unit edisi kedua melalui jalur pre-order resmi.',
                ],
                [
                    'name' => 'Photocard Koleksi Bekas Kondisi Mulus',
                    'price_idr' => 10000,
                    'stock' => 4,
                    'condition' => 'bekas',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Photocard koleksi preloved kondisi terawat baik, tanpa lipatan dan disimpan dalam toploader.',
                ],
            ],
            'Album' => [
                [
                    'name' => 'Album Fisik Edisi Reguler + Photobook',
                    'price_idr' => 250000,
                    'stock' => 10,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => true,
                    'description' => 'Album fisik tersegel lengkap dengan photobook, CD, dan bonus photocard acak.',
                ],
                [
                    'name' => 'Album Mini Edisi Kit Ringkas',
                    'price_idr' => 180000,
                    'stock' => 8,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Album edisi kit dengan kotak kemasan ringkas dan set kartu lirik.',
                ],
                [
                    'name' => 'Album Repackaged Special Edition (Pre-Order)',
                    'price_idr' => 220000,
                    'stock' => 15,
                    'condition' => 'baru',
                    'is_preorder' => true,
                    'preorder_eta' => 'Estimasi 45 hari sejak ditutup',
                    'is_featured' => true,
                    'description' => 'Pemesanan awal album repackaged lengkap dengan poster lipat dan kartu edisi terbatas.',
                ],
                [
                    'name' => 'Album Koleksi Preloved (Unsealed Lengkap)',
                    'price_idr' => 125000,
                    'stock' => 3,
                    'condition' => 'bekas',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Album koleksi preloved kondisi buku dan CD mulus lengkap dengan seluruh isi bawaan.',
                ],
            ],
            'Merchandise' => [
                [
                    'name' => 'Lightstick Edisi Konser Resmi',
                    'price_idr' => 450000,
                    'stock' => 6,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => true,
                    'description' => 'Lightstick resmi dengan kemasan pabrik, strap tangan, dan buku panduan.',
                ],
                [
                    'name' => 'Totebag Kanvas Tebal Sablon Edisi Tur',
                    'price_idr' => 85000,
                    'stock' => 18,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Totebag bahan kanvas tebal dengan ritsleting utama dan saku dalam.',
                ],
                [
                    'name' => 'Poster Gulung A2 + Tabung Pelindung',
                    'price_idr' => 40000,
                    'stock' => 15,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Poster cetak art paper tebal dikirim tergulung di dalam tabung pengaman.',
                ],
                [
                    'name' => 'Binder Koleksi Photocard A5 + 20 Sleeve',
                    'price_idr' => 65000,
                    'stock' => 0,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Binder penyimpanan koleksi kartu ukuran A5 lengkap dengan 20 lembar plastik 4-pocket.',
                ],
            ],
            'Stiker Custom' => [
                [
                    'name' => 'Paket Stiker Custom Vinyl Tahan Air (50 pcs)',
                    'price_idr' => 45000,
                    'stock' => 40,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => true,
                    'description' => 'Cetak stiker bahan vinyl tahan air ukuran 5×5 cm isi 50 lembar, potong persegi atau bulat.',
                ],
                [
                    'name' => 'Paket Stiker Custom Holografis Die-Cut (50 pcs)',
                    'price_idr' => 65000,
                    'stock' => 30,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Cetak stiker efek pelangi holografis dengan potongan mengikuti pola desain (die-cut).',
                ],
                [
                    'name' => 'Paket Stiker Custom Matte Grosir (100 pcs)',
                    'price_idr' => 80000,
                    'stock' => 25,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Paket hemat 100 pcs stiker laminasi doff/matte halus untuk komunitas dan merchandise.',
                ],
            ],
            'Pin Button Custom' => [
                [
                    'name' => 'Paket Pin Button Custom 4,4 cm Glossy (30 pcs)',
                    'price_idr' => 105000,
                    'stock' => 30,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => true,
                    'description' => 'Paket 30 pin peniti bundar diameter 4,4 cm berlaminasi glossy mengilap.',
                ],
                [
                    'name' => 'Paket Pin Button Custom 5,8 cm Matte (30 pcs)',
                    'price_idr' => 120000,
                    'stock' => 25,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'description' => 'Paket 30 pin peniti bundar diameter 5,8 cm dengan permukaan matte elegan.',
                ],
                [
                    'name' => 'Pin Button Edisi Arsip Lama (Nonaktif)',
                    'price_idr' => 35000,
                    'stock' => 5,
                    'condition' => 'baru',
                    'is_preorder' => false,
                    'preorder_eta' => null,
                    'is_featured' => false,
                    'is_active' => false,
                    'description' => 'Contoh produk arsip nonaktif yang tidak ditampilkan di katalog publik.',
                ],
            ],
        ];

        foreach ($data as $categoryName => $products) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName],
            );

            foreach ($products as $row) {
                $stock = (int) $row['stock'];
                $product = Product::firstOrCreate(
                    ['name' => $row['name']],
                    [
                        'category_id' => $category->id,
                        'description' => $row['description'],
                        'price_idr' => (int) $row['price_idr'],
                        'condition' => $row['condition'] ?? 'baru',
                        'is_preorder' => (bool) ($row['is_preorder'] ?? false),
                        'preorder_eta' => $row['preorder_eta'] ?? null,
                        'is_featured' => (bool) ($row['is_featured'] ?? false),
                        'stock_on_hand' => $stock,
                        'stock_reserved' => 0,
                        'is_active' => $row['is_active'] ?? true,
                    ],
                );

                if ($stock > 0 && ! StockMovement::where('product_id', $product->id)->exists()) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'penyesuaian',
                        'delta_on_hand' => $stock,
                        'delta_reserved' => 0,
                        'order_id' => null,
                        'actor_type' => 'sistem',
                        'actor_id' => null,
                        'note' => 'Stok awal (seed contoh)',
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }
}
