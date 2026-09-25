<?php

namespace App\Http\Controllers;

use App\Models\Product;

class HomeController extends Controller
{
    /**
     * Beranda — halaman depan ala koran: hero, cara kerja, teaser downloader,
     * dan enam produk terbaru.
     */
    public function index()
    {
        $products = Product::active()->latest()->limit(6)->get();

        return view('index', compact('products'));
    }

    /**
     * Tentang — penjelasan singkat cara bayar transfer manual via WhatsApp.
     */
    public function about()
    {
        return view('about');
    }

    /**
     * Downloader — halaman statis (di versi aslinya alat ini benar-benar
     * mengunduh video; di port Laravel ini hanya tiruan tampilan).
     */
    public function downloader()
    {
        return view('downloader');
    }
}
