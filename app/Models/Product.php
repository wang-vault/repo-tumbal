<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Katalog toko. Cermin dari tabel `products` milik Anubis Store
 * (lihat supabase/store/001_schema.sql di repo aslinya).
 *
 * Harga disimpan dalam RUPIAH PENUH (integer, tanpa desimal).
 * Contoh: 25000 = Rp25.000
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /**
     * Kolom yang boleh diisi massal (dipakai ProductController & seeder).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'image_url',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Hanya produk aktif yang tampil di katalog publik / beranda.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Pesanan yang memakai produk ini. Dipakai ProductController::destroy()
     * untuk menolak penghapusan produk yang sudah pernah dipesan.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Harga siap tampil: 25000 -> "Rp25.000"
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp'.number_format($this->price, 0, ',', '.');
    }

    /**
     * Status singkat untuk badge di kartu produk.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Aktif' : 'Nonaktif';
    }
}
