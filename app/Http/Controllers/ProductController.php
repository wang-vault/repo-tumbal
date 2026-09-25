<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Baris per halaman katalog. Cukup panjang untuk dibaca tanpa menggulir
     * berlebihan, cukup pendek supaya pagination-nya benar-benar terpakai.
     */
    public const PER_PAGE = 12;

    /**
     * Katalog + daftar kelola produk. Mendukung pencarian ?q=... (form GET,
     * tanpa JavaScript — sama seperti katalog pembeli di Anubis).
     *
     * Tamu hanya melihat produk aktif; penjual yang sudah masuk melihat
     * semuanya (termasuk produk nonaktif untuk arsip).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->when($request->user() === null, fn ($query) => $query->active())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            // Produk buatan seeder bisa punya created_at yang sama persis, jadi
            // id ikut diurutkan sebagai pemecah seri — tanpa ini baris yang sama
            // bisa muncul di dua halaman.
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            // Kata kunci pencarian ikut terbawa ke tautan halaman berikutnya.
            ->withQueryString();

        return view('products.index', compact('products', 'search'));
    }

    public function create(): View
    {
        return view('products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Product::create($this->validated($request));

        return redirect()
            ->route('product-list')
            ->with('success', 'Produk "'.$product->name.'" berhasil ditambah.');
    }

    public function show(Product $product): View
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request));

        return redirect()
            ->route('product-list')
            ->with('success', 'Produk "'.$product->name.'" berhasil diubah.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('product-list')
            ->with('success', 'Produk "'.$name.'" berhasil dihapus.');
    }

    /**
     * Aturan validasi = CHECK constraint tabel products milik Anubis:
     * nama 2-120 karakter, harga Rp1.000 - Rp100.000.000.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        // Kolom yang tidak dikirim form tetap harus punya nilai.
        $data['description'] = $data['description'] ?? '';
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
