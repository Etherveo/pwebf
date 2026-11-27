<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Public catalog, filters, product detail & product photo streaming.
class CatalogController extends Controller
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function index(Request $request): View|RedirectResponse
    {
        // SHP-03: keyword 2-100 characters. Too short / too long is a
        // validation error back to the same listing URL (without the
        // bad keyword), not a silent ignore and not a crash.
        if ($request->filled('q')) {
            $length = mb_strlen((string) $request->input('q'));
            if ($length < 2 || $length > 100) {
                return redirect()->to($request->url())
                    ->withErrors(['q' => 'Kata kunci pencarian minimal 2 dan maksimal 100 karakter.'])
                    ->withInput($request->except('q'));
            }
        }

        $sort = (string) $request->input('urut', 'nama');

        $query = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->when($request->filled('kategori'), fn ($q) => $q->where('category_id', $request->input('kategori')))
            ->when(in_array($request->input('kondisi'), Product::CONDITIONS, true),
                fn ($q) => $q->where('condition', $request->input('kondisi')))
            ->when($request->input('tipe') === 'preorder', fn ($q) => $q->where('is_preorder', true))
            ->when($request->input('tipe') === 'ready', fn ($q) => $q->where('is_preorder', false))
            ->when($request->input('stok') === 'tersedia', fn ($q) => $q->whereRaw('stock_on_hand > stock_reserved'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->escapedLikeTerm((string) $request->input('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->whereRaw("lower(name) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("lower(description) LIKE ? ESCAPE '!'", [$term]);
                });
            });

        match ($sort) {
            'terbaru' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'harga_asc' => $query->orderBy('price_idr')->orderBy('name'),
            'harga_desc' => $query->orderByDesc('price_idr')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('shop.katalog-index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function show(int $produk, Request $request): View
    {
        $product = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->findOrFail($produk);

        $user = $request->user('web');
        if ($user instanceof User) {
            $sessionRef = $request->hasSession() ? $request->session()->getId() : null;
            $this->activity->recordView($user, $product, $sessionRef);
        }

        return view('shop.produk-detail', ['product' => $product]);
    }

    public function photo(int $produk, Request $request): StreamedResponse
    {
        $product = Product::query()->findOrFail($produk);
        $isAdmin = $request->user('web')?->isAdmin() === true;

        if (! $product->is_active && ! $isAdmin) {
            abort(404);
        }

        if (! $product->hasImage() || ! Storage::disk('local')->exists($product->image_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($product->image_path);
    }

    // SHP-03: % and _ in the keyword are literal characters, not LIKE
    // wildcards, so searching "100%" does not match every product.
    private function escapedLikeTerm(string $keyword): string
    {
        $escaped = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            mb_strtolower($keyword),
        );

        return '%'.$escaped.'%';
    }
}
