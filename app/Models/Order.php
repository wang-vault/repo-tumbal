<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pesanan pembeli. Pembayaran manual: pembeli memesan, transfer di luar aplikasi,
 * menekan "saya sudah transfer", lalu penjual memverifikasi dan menaikkan status.
 *
 * Alur status (sama dengan order_status milik Anubis, tanpa EXPIRED karena port
 * ini tidak punya mekanisme batas waktu bayar):
 *
 *   PENDING ──▶ PAID ──▶ PROCESSING ──▶ DONE
 */
class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAID = 'PAID';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_DONE = 'DONE';

    /** Urutan alur status — dipakai linimasa di halaman pesanan. */
    public const FLOW = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_PROCESSING,
        self::STATUS_DONE,
    ];

    /** Perpindahan status yang diizinkan untuk penjual. */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PAID],
        self::STATUS_PAID => [self::STATUS_PROCESSING],
        self::STATUS_PROCESSING => [self::STATUS_DONE],
        self::STATUS_DONE => [],
    ];

    /** Label Indonesia untuk badge dan linimasa. */
    public const LABELS = [
        self::STATUS_PENDING => 'Menunggu pembayaran',
        self::STATUS_PAID => 'Sudah dibayar',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_DONE => 'Selesai',
    ];

    /** Batas jumlah per pesanan (aturan checkout Anubis). */
    public const MAX_QUANTITY = 20;

    /** Alfabet kode pesanan: tanpa 0/O dan 1/I/L supaya tidak salah baca. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_code',
        'product_id',
        'product_name_snapshot',
        'unit_price_snapshot',
        'quantity',
        'total_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'manual_claim_at',
        'manual_claim_note',
        'manual_claim_reference',
        'manual_reviewed_at',
        'manual_review_status',
        'manual_review_note',
        'paid_at',
        'buyer_name_snapshot',
        'buyer_whatsapp_snapshot',
        'buyer_email_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'integer',
            'quantity' => 'integer',
            'total_amount' => 'integer',
            'manual_claim_at' => 'datetime',
            'manual_reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Saring daftar pesanan berdasarkan status; null/'' berarti semua status.
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(
            $status !== null && $status !== '' && in_array($status, self::TRANSITIONS, true),
            fn (Builder $inner) => $inner->where('order_status', $status)
        );
    }

    /**
     * Pesanan selesai — sumber data halaman testimoni.
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_DONE);
    }

    public function canTransitionTo(?string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->order_status] ?? [], true);
    }

    /**
     * Pilihan status berikutnya untuk tombol di halaman kelola penjual.
     *
     * @return list<string>
     */
    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->order_status] ?? [];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::LABELS[$this->order_status] ?? $this->order_status;
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return 'Rp'.number_format($this->unit_price_snapshot, 0, ',', '.');
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp'.number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Pembeli sudah menekan "saya sudah transfer".
     */
    public function isClaimed(): bool
    {
        return $this->manual_claim_at !== null;
    }

    /**
     * Klaim pembeli masih menunggu keputusan penjual.
     */
    public function isAwaitingReview(): bool
    {
        return $this->isClaimed()
            && $this->manual_review_status === null
            && $this->order_status === self::STATUS_PENDING;
    }

    public function getMaskedBuyerNameAttribute(): string
    {
        return self::maskBuyerName($this->buyer_name_snapshot);
    }

    /**
     * Pendekkan nama pembeli untuk halaman publik /testimoni:
     *
     *   "Budi Santoso"      -> "Budi S."
     *   "Budi Santoso Andi" -> "Budi A."
     *   "Rizky"             -> "R***y"
     *   "" / null           -> "Pembeli"
     *
     * Port dari maskBuyerName() di src/lib/testimonials.ts milik Anubis.
     */
    public static function maskBuyerName(?string $raw): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $raw));

        if ($name === '') {
            return 'Pembeli';
        }

        $parts = explode(' ', $name);

        if (count($parts) === 1) {
            $word = $parts[0];

            if (mb_strlen($word) <= 2) {
                return mb_substr($word, 0, 1).'***';
            }

            return mb_substr($word, 0, 1).'***'.mb_substr($word, -1);
        }

        $lastInitial = mb_substr($parts[count($parts) - 1], 0, 1);

        return $lastInitial !== '' ? $parts[0].' '.$lastInitial.'.' : $parts[0];
    }

    /**
     * Kode pesanan publik: ORD-YYYYMMDD-XXXXXX.
     *
     * Port dari src/lib/order-code.ts milik Anubis — tanggal memakai zona WIB
     * (Asia/Jakarta) dan 6 karakter acak dari alfabet tanpa 0/O/1/I/L.
     */
    public static function generateCode(?Carbon $now = null): string
    {
        $date = ($now ?? now())->copy()->setTimezone('Asia/Jakarta')->format('Ymd');

        $last = strlen(self::CODE_ALPHABET) - 1;
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::CODE_ALPHABET[random_int(0, $last)];
        }

        return "ORD-{$date}-{$suffix}";
    }

    /**
     * Kode yang dijamin belum terpakai (tabrakan sangat kecil kemungkinannya,
     * tapi kolomnya unique jadi tetap diperiksa).
     */
    public static function generateUniqueCode(?Carbon $now = null): string
    {
        do {
            $code = self::generateCode($now);
        } while (static::where('order_code', $code)->exists());

        return $code;
    }
}
