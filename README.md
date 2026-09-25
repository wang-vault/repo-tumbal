# Anubis Store — versi Laravel

Port **tampilan depan** [Anubis Store](https://github.com/wang-vault/anubis) (aslinya Next.js 15 +
Supabase) ke **Laravel 12 + Blade**, dengan satu halaman template yang dipakai semua halaman
(`<x-layouts.app>`), beranda `index.blade.php`, CRUD produk lengkap, dan alur pesanan transfer
manual di SQLite.

Bentuk proyeknya mengikuti contoh [`qwerti1945/dasar_laravel`](https://github.com/qwerti1945/dasar_laravel):
skeleton Laravel standar + layout component `resources/views/components/layouts/app.blade.php` +
controller/model/migration/factory/seeder untuk tiap entitas (di contoh: `Student`, di sini:
`Product` dan `Order`).

> **Yang diport: etalase + pesanan manual.** Checkout, kode pesanan, klaim "sudah transfer",
> verifikasi penjual, dan `/testimoni` **sudah ikut**. Login di sini session-based lokal (bukan
> Supabase Auth), dan yang tetap **tidak** ikut adalah gerbang pembayaran (Stenly, Yobasepay,
> QRIS otomatis), chat WhatsApp, serta notifikasi Telegram — semuanya tetap hidup di aplikasi
> aslinya. Halaman downloader juga cuma tiruan tampilan.

---

## Yang ada di sini

| Bagian | Isi |
|---|---|
| Beranda `/` | Hero "Belanja gampang, *kabar* pembayaran datang cepat", **kotak pencarian produk**, 3 langkah cara kerja, teaser downloader, 6 produk terbaru |
| Katalog `/products` | Daftar semua produk + pencarian `?q=...` (form GET, jalan tanpa JavaScript) + pagination 12 baris per halaman |
| CRUD produk | Tambah, lihat detail, ubah, hapus — dengan validasi & pesan flash |
| Checkout `/checkout/{id}` | Form untuk tamu (tanpa daftar akun): nama, WhatsApp, jumlah — nama & harga produk **difoto** (snapshot) ke pesanan |
| Pesanan `/orders/{kode}` | Kode `ORD-YYYYMMDD-XXXXXX`, halaman rincian terbuka lewat kodenya, alur status `PENDING → PAID → PROCESSING → DONE` |
| Klaim transfer | Pembeli menandai "sudah transfer" (+ nomor referensi & catatan); status **tetap** `PENDING` sampai penjual menyetujui |
| Kelola pesanan `/orders` | Khusus penjual: daftar semua pesanan, dua saringan (`?status=PAID`, `?payment=PENDING`), ubah, dan hapus pesanan |
| Testimoni `/testimoni` | Otomatis dari pesanan `DONE` (maks 20 terbaru), nama pembeli dipendekkan jadi "Budi S.", tanpa nomor WA/kode/total |
| Tentang `/about` | Penjelasan alur transfer manual via WhatsApp |
| Downloader `/downloader` | Halaman statis 3 platform (TikTok, YouTube, Instagram) |
| Tampilan | CSS statis `public/css/anubis.css` — gaya koran: kertas hangat, tinta, aksen oxblood, sudut tajam, bayangan offset |
| Login penjual | `POST /login` berbasis session + pembatasan 5 percobaan/menit; semua rute tulis dilindungi middleware `auth` |
| Batas tulis | `throttle:product-write` 20/menit/penjual, `order-create` 10/menit/IP, `order-claim` 5/menit/IP, `order-status` 30/menit/penjual — lebih dari itu muncul halaman 429 |
| Halaman error | 403, 404, 419, 429, 500 memakai tata letak yang sama (`resources/views/errors/`), bukan halaman bawaan Laravel |
| CI | `.github/workflows/ci.yml` — matrix PHP 8.2/8.3/8.4 (install, `.env`, migrasi+seed, kompilasi Blade, `route:list`, `php artisan test`) + job `pint --test` |
| Test | 65 pengujian: `AuthTest` (13 — login & hak akses), `ProductTest` (16 — katalog, CRUD, pagination, pencarian, halaman error, throttle), `OrderTest` (29 — checkout, kode pesanan, snapshot, klaim, alur status, ubah & hapus pesanan, status pembayaran), `TestimonialTest` (5 — privasi & batas 20), `ExampleTest` bawaan skeleton (2 — feature + unit) |

**Tidak perlu `npm install` / `npm run build`.** Layout memakai `<link rel="stylesheet">` ke CSS
statis, bukan `@vite`, jadi `php artisan serve` langsung menampilkan tampilan lengkap. (Vite +
Tailwind bawaan skeleton tetap ada dan tetap dipakai `resources/views/welcome.blade.php` kalau
kamu mau memakainya nanti.)

---

## Login & hak akses

Katalog, checkout, dan rincian pesanan **boleh dibaca siapa saja**; yang dilindungi hanya aksi
tulis milik penjual.

| Siapa | Boleh |
|---|---|
| Tamu / pembeli | `/`, `/about`, `/downloader`, `/testimoni`, `/products` (hanya produk **aktif**), `/products/{id}`, `/checkout/{id}` (GET + POST), `/orders/{kode}` (rincian pesanan lewat kodenya), `POST /orders/{kode}/claim` |
| Penjual (sudah masuk) | semua di atas + `/products/create`, `POST /products`, `/products/{id}/edit`, `PUT`, `DELETE`, melihat produk nonaktif di daftar kelola, `/orders` (semua pesanan), `POST /orders/{kode}/status`, `POST /orders/{kode}/reject-claim`, `/orders/{kode}/edit`, `PUT /orders/{kode}`, `DELETE /orders/{kode}` |

> Pembeli **tidak punya akun**. Kode pesanan `ORD-YYYYMMDD-XXXXXX` merangkap jadi alamat dan
> kunci akses: siapa pun yang memegang kode itu bisa membuka dan mengklaim pesanannya — sama
> seperti tautan lacak di aplikasi aslinya. Karena itu kode tidak bisa ditebak berurutan
> (6 karakter acak tanpa `0/O/1/I/L`) dan rute mengikat model lewat `{order:order_code}`.

Akun demo dibuat oleh seeder:

```
email    : admin@anubis.test
password : password
```

> Ganti password itu sebelum dipakai di lingkungan nyata (`php artisan tinker` →
> `User::where('email','admin@anubis.test')->first()->update(['password' => Hash::make('...')])`).

Login ini session-based biasa (bukan Supabase Auth seperti di aplikasi aslinya):
`Auth::attempt()`, session di-regenerate setelah masuk (anti session fixation),
`Auth::logout()` + `session()->invalidate()` saat keluar, dan maksimal 5 percobaan
gagal per menit untuk tiap kombinasi email + IP.

Arah pengalihan diatur di `bootstrap/app.php`:

```php
$middleware->redirectGuestsTo(fn () => route('login'));   // tamu -> /login
$middleware->redirectUsersTo(fn () => route('home'));     // sudah masuk -> /
```

Setelah login, penjual dikembalikan ke halaman yang tadi diblokir
(`redirect()->intended()`), misalnya `/products/create`.

## Persyaratan

- **PHP ^8.2** dengan ekstensi: `pdo_sqlite`, `mbstring`, `openssl`, `ctype`, `json`, `tokenizer`,
  `xml`, `curl`, `fileinfo`, `bcmath`
- **Composer 2.x**
- SQLite (bawaan PHP) — tidak perlu MySQL

Cek cepat:

```bash
php -v
php -m | grep -Ei "pdo_sqlite|mbstring|openssl|tokenizer|xml|curl|fileinfo|bcmath"
```

## Menjalankan

```bash
git clone https://github.com/wang-vault/repo-tumbal.git anubis-laravel
cd anubis-laravel

composer install                       # pakai composer.lock yang sudah disertakan (Laravel 12.68)
cp .env.example .env
php artisan key:generate
touch database/database.sqlite         # database SQLite kosong
php artisan migrate --seed             # tabel + akun penjual demo + 10 produk + 6 pesanan contoh
php artisan serve                      # http://127.0.0.1:8000
```

Menjalankan test:

```bash
php artisan test                       # semua test (65)
php artisan test --filter=AuthTest         # khusus login & hak akses
php artisan test --filter=ProductTest      # khusus produk
php artisan test --filter=OrderTest        # khusus checkout, klaim, alur status
php artisan test --filter=TestimonialTest  # khusus testimoni & privasi data pembeli
```

Login di browser: buka <http://127.0.0.1:8000/login> dengan `admin@anubis.test` / `password`.

Mencoba alur pesan tanpa login: buka salah satu produk → **Pesan Produk Ini** → isi form →
simpan kodenya (`ORD-…`) → di halaman rincian klik **Saya sudah transfer** → masuk sebagai
penjual → buka `/orders` → setujui klaimnya sampai status `DONE` → lihat hasilnya di `/testimoni`.

> Tidak punya PHP/Composer di mesin yang dipakai? Direktori `preview-kit/` berisi perkakas untuk menjalankan
> aplikasi lewat **PHP-WASM**: `bash preview-kit/setup.sh --demo` (mengunduh PHP-WASM lewat npm,
> 111 paket vendor dari GitHub, merakit autoloader, migrasi + seed) lalu
> `APP_ROOT=/tmp/verify/app PORT=8080 node preview-kit/server.mjs`. Test juga bisa dijalankan
> dengan `bash preview-kit/run-tests.sh`. Semua ini perkakas sandbox — di mesin biasa cukup
> `composer install` + `php artisan serve` + `php artisan test`.

## CI & gaya kode

Workflow `.github/workflows/ci.yml` jalan tiap `push` ke `main` dan tiap pull request:

| Job | Isi |
|---|---|
| `tests` | matrix PHP **8.2 / 8.3 / 8.4**: `composer install`, `cp .env.example .env` + `key:generate`, `migrate --seed`, `view:cache` (ikut menangkap salah sintaks Blade), `route:list`, lalu `php artisan test` |
| `style` | `pint --test` dengan preset **laravel** (`pint.json`). Pint dipasang global di runner, tidak masuk `composer.lock` supaya lock file tetap ramping |

Merapikan gaya kode di mesin sendiri:

```bash
composer global require laravel/pint   # sekali saja
pint                                   # perbaiki langsung
pint --test                            # cuma memeriksa (ini yang dijalankan CI)
pint app/Models/Order.php              # satu berkas saja
```

Seluruh repo ini berlisensi **MIT** (berkas `LICENSE`).

## Rute

| Method | URI | Nama | Middleware | Controller |
|---|---|---|---|---|
| GET | `/` | `home` | `web` | `HomeController@index` |
| GET | `/about` | `about` | `web` | `HomeController@about` |
| GET | `/downloader` | `downloader` | `web` | `HomeController@downloader` |
| GET | `/testimoni` | `testimoni` | `web` | `TestimonialController@index` |
| GET | `/products` | `product-list` | `web` | `ProductController@index` |
| GET | `/products/{product}` | `product-show` | `web` | `ProductController@show` |
| GET | `/checkout/{product}` | `checkout` | `web` | `CheckoutController@create` |
| POST | `/checkout/{product}` | `order-store` | `web, throttle:order-create` | `CheckoutController@store` |
| GET | `/orders/{order:order_code}` | `order-show` | `web` | `OrderController@show` |
| POST | `/orders/{order:order_code}/claim` | `order-claim` | `web, throttle:order-claim` | `OrderController@claim` |
| GET | `/login` | `login` | `web, guest` | `Auth\LoginController@show` |
| POST | `/login` | `login.store` | `web, guest` | `Auth\LoginController@store` |
| POST | `/logout` | `logout` | `web, auth` | `Auth\LoginController@destroy` |
| GET | `/products/create` | `product-create` | `web, auth` | `ProductController@create` |
| POST | `/products` | `product-store` | `web, auth, throttle:product-write` | `ProductController@store` |
| GET | `/products/{product}/edit` | `product-edit` | `web, auth` | `ProductController@edit` |
| PUT | `/products/{product}` | `product-update` | `web, auth, throttle:product-write` | `ProductController@update` |
| DELETE | `/products/{product}` | `product-destroy` | `web, auth, throttle:product-write` | `ProductController@destroy` |
| GET | `/orders` | `order-list` | `web, auth` | `OrderController@index` |
| POST | `/orders/{order:order_code}/status` | `order-status` | `web, auth, throttle:order-status` | `OrderController@updateStatus` |
| POST | `/orders/{order:order_code}/reject-claim` | `order-claim-reject` | `web, auth, throttle:order-status` | `OrderController@rejectClaim` |
| GET | `/orders/{order:order_code}/edit` | `order-edit` | `web, auth` | `OrderController@edit` |
| PUT | `/orders/{order:order_code}` | `order-update` | `web, auth, throttle:order-status` | `OrderController@update` |
| DELETE | `/orders/{order:order_code}` | `order-destroy` | `web, auth, throttle:order-status` | `OrderController@destroy` |
| GET | `/up` | — | — | health check bawaan Laravel |

Urutan pendaftaran di `routes/web.php` penting: `/products/create` dan
`/products/{product}/edit` harus didefinisikan **sebelum** `/products/{product}`,
kalau tidak kata `create`/`edit` ditangkap sebagai parameter `{product}`. `/orders` (daftar kelola)
dan `/orders/{order:order_code}` tidak bentrok karena jumlah segmennya berbeda.

Penamaan rute (`product-list`, `product-create`, …) sengaja meniru gaya `student-*` di repo contoh.

Membuka form (`product-create`, `product-edit`, `checkout`) **tidak** ikut dibatasi throttle —
yang dibatasi hanya aksi menulis. Semua limiter didefinisikan di
`AppServiceProvider::configureRateLimiting()`; kalau dilanggar, Laravel mengirim 429 yang dirender
`resources/views/errors/429.blade.php`.

| Limiter | Batas | Kunci | Melindungi |
|---|---|---|---|
| `login` | 5/menit | email + IP | percobaan masuk |
| `product-write` | 20/menit | penjual | tambah/ubah/hapus produk |
| `order-create` | 10/menit | IP | `POST /checkout/{product}` (tamu, tanpa akun) |
| `order-claim` | 5/menit | IP | `POST /orders/{kode}/claim` (tamu) |
| `order-status` | 30/menit | penjual | ubah status, tolak klaim, ubah & hapus pesanan |

## Skema `products`

Meniru tabel `products` milik Anubis (`supabase/store/001_schema.sql`):

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint auto | primary key |
| `name` | string | wajib, 2-120 karakter |
| `description` | text | boleh kosong, maks. 2000 karakter |
| `price` | unsigned bigint | wajib, **Rupiah penuh** tanpa desimal, Rp1.000 - Rp100.000.000 |
| `image_url` | string | boleh kosong, harus URL valid |
| `is_active` | boolean | default `true`; produk nonaktif tidak muncul di beranda |
| `created_at` / `updated_at` | timestamp | otomatis |

Index `[is_active, created_at]` untuk katalog publik (produk aktif, terbaru lebih dulu).

## Skema `orders`

Meniru tabel `orders` milik Anubis (`supabase/store/001_schema.sql`), dipangkas ke jalur `MANUAL` saja:

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint auto | primary key |
| `order_code` | string unique | `ORD-YYYYMMDD-XXXXXX` — tanggal dalam **WIB** (UTC+7), 6 karakter acak dari alfabet `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (tanpa `0/O/1/I/L` supaya tidak mirip saat dibaca) |
| `product_id` | FK → `products` | `ON DELETE RESTRICT` — produk yang sudah punya pesanan tidak bisa dihapus |
| `product_name_snapshot` | string | nama produk **saat dipesan** |
| `unit_price_snapshot` | unsigned bigint | harga satuan saat dipesan (Rupiah penuh) |
| `quantity` | unsigned smallint | 1-20 per pesanan |
| `total_amount` | unsigned bigint | `unit_price_snapshot × quantity` — dihitung ulang otomatis oleh model setiap pesanan disimpan |
| `payment_method` | string | selalu `MANUAL` di port ini |
| `payment_status` | string | `PENDING` \| `PAID` — penjual bisa mengubahnya lewat form ubah pesanan; tidak bisa ditarik mundur kalau pesanan sudah naik status |
| `order_status` | string | `PENDING` \| `PAID` \| `PROCESSING` \| `DONE` |
| `manual_claim_at` / `_note` / `_reference` | timestamp / text / string | isian pembeli saat menandai "sudah transfer" |
| `manual_reviewed_at` / `_status` / `_note` | timestamp / string / text | keputusan penjual: `APPROVED` \| `REJECTED` + catatannya |
| `paid_at` | timestamp nullable | terisi otomatis saat status jadi `PAID` |
| `buyer_name_snapshot` | string | wajib, 2-80 karakter |
| `buyer_whatsapp_snapshot` | string | dinormalkan ke `62…` (dari `0812…`, `+62 812…`, `62-812-…`) |
| `buyer_email_snapshot` | string nullable | boleh kosong |
| `created_at` / `updated_at` | timestamp | otomatis |

Index `[order_status, created_at]` untuk daftar kelola + filter status.

## Alur pesanan

```
  PENDING ──setujui klaim──> PAID ──> PROCESSING ──> DONE
     │                        (hanya maju: tidak bisa melompat / mundur)
     └── pembeli klaim "sudah transfer" → status TETAP PENDING, menunggu review penjual
```

1. Pembeli buka `/checkout/{id}` → isi nama, WhatsApp, jumlah → pesanan dibuat dengan status
   `PENDING` + kode `ORD-…`. Nama dan harga produk **difoto** ke kolom snapshot, jadi menyunting
   produk sesudahnya tidak mengubah pesanan yang sudah ada.
2. Pembeli transfer manual, lalu buka `/orders/{kode}` → **Saya sudah transfer** (boleh ditambah
   nomor referensi + catatan). Status belum naik — klaim hanya menandai pesanan ini menunggu
   pemeriksaan, persis jalur manual di aplikasi aslinya.
3. Penjual cek mutasi lewat `/orders` → **setujui** (status naik ke `PAID`, `paid_at` terisi,
   review `APPROVED`) atau **tolak** (klaim dibersihkan, review `REJECTED`, pembeli boleh
   mengklaim ulang).
4. Penjual menaikkan `PAID → PROCESSING → DONE`. Lompatan (`PENDING → DONE`) dan langkah mundur
   ditolak `Order::canTransitionTo()` dengan pesan flash.
5. Pesanan `DONE` otomatis muncul sebagai testimoni di `/testimoni` (maks 20 terbaru).
6. Penjual juga bisa **mengubah** pesanan lewat `/orders/{kode}/edit` (jumlah, harga satuan, data
   pembeli, dan status pembayaran — total selalu dihitung ulang oleh model) serta **menghapusnya**
   kalau itu pesanan uji atau duplikat. Menghapus pesanan sekaligus membuka kunci produk yang
   tadinya tidak bisa dihapus karena sudah pernah dipesan.

Testimoni hanya membaca 4 kolom aman: `product_name_snapshot`, `quantity`, nama pembeli yang
dipendekkan (`Order::maskBuyerName()` — "Budi Santoso" → "Budi S.", "Rizky" → "R***y"), dan
tanggal selesai. Kode pesanan, nomor WhatsApp, email, dan nominal **tidak pernah** dikirim ke
halaman itu.

## Berkas yang ditambahkan / diubah

Ditambahkan di atas skeleton Laravel standar:

```
app/Http/Controllers/Auth/LoginController.php   login/logout + pembatasan percobaan
app/Http/Controllers/HomeController.php         beranda, tentang, downloader
app/Http/Controllers/ProductController.php      CRUD produk + pencarian + pagination 12/halaman
app/Http/Controllers/CheckoutController.php     form checkout tamu + pembuatan pesanan + normalisasi WA
app/Http/Controllers/OrderController.php        rincian, klaim, daftar kelola + filter, ubah status, tolak klaim
app/Http/Controllers/TestimonialController.php  testimoni dari pesanan DONE
app/Models/Product.php                          scope active(), formatted_price, status_label
app/Models/Order.php                            generateCode(), maskBuyerName(), canTransitionTo(), scope status()/done()
database/migrations/2026_09_25_000000_create_products_table.php
database/migrations/2026_09_25_000100_create_orders_table.php
database/factories/ProductFactory.php           + state inactive()
database/factories/OrderFactory.php             + state claimed(), paid(), processing(), done()
database/seeders/UserSeeder.php                 akun penjual demo (admin@anubis.test)
database/seeders/ProductSeeder.php              6 produk pilihan + 4 acak
database/seeders/OrderSeeder.php                6 pesanan contoh mencakup semua status (harus jalan setelah ProductSeeder)
database/seeders/DatabaseSeeder.php             memanggil UserSeeder + ProductSeeder + OrderSeeder
resources/views/components/layouts/app.blade.php TEMPLATE: masthead + nav + flash + footer + {{ $slot }}
resources/views/index.blade.php                 beranda (dipakai route /)
resources/views/auth/login.blade.php            form masuk
resources/views/about.blade.php
resources/views/downloader.blade.php
resources/views/products/index.blade.php
resources/views/products/create.blade.php
resources/views/products/edit.blade.php
resources/views/products/show.blade.php
resources/views/checkout.blade.php              form pesan untuk pembeli (tanpa perlu akun)
resources/views/orders/show.blade.php           rincian pesanan + garis waktu status + form klaim/verifikasi
resources/views/orders/index.blade.php          daftar kelola + saringan status & pembayaran + ubah/hapus
resources/views/orders/edit.blade.php           form ubah pesanan + zona hapus
resources/views/testimoni.blade.php             kartu testimoni dari pesanan selesai
resources/views/products/partials/form.blade.php form bersama create/edit
resources/views/partials/pagination.blade.php   tampilan pagination sendiri (bukan class Tailwind)
resources/views/errors/partials/notice.blade.php kerangka bersama halaman error
resources/views/errors/403.blade.php            akses ditolak
resources/views/errors/404.blade.php            alamat/produk tidak ada
resources/views/errors/419.blade.php            sesi atau token CSRF kedaluwarsa
resources/views/errors/429.blade.php            kena batas throttle (tulis produk / pesanan)
resources/views/errors/500.blade.php            kesalahan server
public/css/anubis.css                           seluruh tampilan (tanpa build step)
tests/Feature/AuthTest.php                      13 pengujian login & hak akses
tests/Feature/ProductTest.php                   15 pengujian katalog, CRUD, validasi, pagination, halaman error, throttle
tests/Feature/OrderTest.php                     18 pengujian checkout, kode pesanan, snapshot harga, klaim, alur status, throttle
tests/Feature/TestimonialTest.php               5 pengujian testimoni: hanya DONE, penyamaran nama, tanpa bocoran data, batas 20
.github/workflows/ci.yml                        CI: test (PHP 8.2/8.3/8.4) + pemeriksaan gaya Pint
pint.json                                       preset gaya kode: laravel
LICENSE                                         MIT
preview-kit/                                    perkakas sandbox preview (PHP-WASM) — bukan bagian aplikasi
composer.lock                                   dari Laravel 12.68.0, content-hash cocok
```

Diubah dari skeleton: `routes/web.php`, `bootstrap/app.php` (arah redirect `auth`/`guest`),
`app/Providers/AppServiceProvider.php` (tampilan pagination default + limiter `product-write`,
`order-create`, `order-claim`, `order-status`), `.env.example` (`APP_NAME`, `APP_FAKER_LOCALE=id_ID`),
`tests/Feature/ExampleTest.php` (pakai `RefreshDatabase`), `README.md`.

Cara memakai template di halaman mana pun:

```blade
<x-layouts.app title="Judul Halaman">
    <div class="container-x stack">
        ... isi halaman ...
    </div>
</x-layouts.app>
```

Properti `title` opsional — kalau diisi, judul tab jadi `Judul Halaman · Anubis Store`.

## Catatan `composer.lock`

`laravel/laravel` tidak menyimpan `composer.lock`, jadi di sini lock file disertakan supaya hasil
`composer install` sama di semua mesin (Laravel **v12.68.0**, 76 paket + 35 paket dev).
Content hash lock file sudah diverifikasi cocok dengan `composer.json`.

Kalau nanti kamu menambah paket baru, jalankan `composer require <paket>` dan commit `composer.lock`
yang berubah.

## Batasan port ini (yang sengaja belum ada)

Port ini memindahkan **etalase toko** (beranda, katalog, detail produk), **login penjual**,
**CRUD produk**, **alur pesanan transfer manual** (checkout → klaim → verifikasi → selesai), dan
**`/testimoni`**. Aplikasi Anubis aslinya lebih besar; bagian berikut belum ikut dipindahkan.

| Belum ada | Kondisi di Anubis asli | Catatan |
|---|---|---|
| Gerbang pembayaran | Stenly, Yobasepay, dan QRIS otomatis (`payment_id`, `payment_url`, `qr_image_url`, `payment_expired_at`, `last_payment_checked_at`) | port ini hanya jalur `MANUAL`: pembeli transfer sendiri, lalu mengklaim; penjual memverifikasi mutasi rekening |
| Halaman `/orders/{code}/receipt` & `/pay/{code}` | struk pembayaran + halaman bayar | angka lengkapnya sudah ada di `/orders/{kode}`; dua halaman itu belum dibuat |
| Status `EXPIRED` / pembayaran `FAILED` + tenggat bayar | pesanan kedaluwarsa sendiri kalau belum dibayar sampai `payment_expired_at`; pembayaran juga bisa ditandai gagal | tidak ada mekanisme tenggat di sini: alur berhenti di `DONE`, status bayar hanya `PENDING`/`PAID`, dan pesanan lama tetap `PENDING` sampai penjual menaikkannya |
| Kolom `account_id` di `orders` | pesanan tertaut ke akun pembeli (Supabase Auth) | pembeli di sini tamu, jadi `order_code` merangkap kunci akses — tidak ada akun untuk ditautkan |
| Chat WhatsApp & notifikasi Telegram | `waMeUrl()` ke penjual, `telegram_notified_at`, `manual_notified_at` | nomor WA pembeli disimpan & dinormalkan ke `62…`, tapi tidak ada pesan keluar dari aplikasi |
| Tabel `manual_payment_settings` | rekening/QRIS tujuan yang bisa diubah admin | di sini instruksinya teks statis: pembeli diminta menghubungi penjual lewat WhatsApp |
| Panel admin terpisah | `/admin` (dashboard), `/admin/products`, `/admin/orders`, `/admin/settings` | di sini digabung: `/products` berubah jadi daftar kelola setelah penjual masuk |
| Downloader yang berfungsi | benar-benar mengunduh dari YouTube/audio/Instagram/TikTok lewat 8 rute API | di sini hanya tiruan tampilan, sesuai kesepakatan awal |
| Registrasi, lupa/reset password, verifikasi email | ada di `/auth/*` | di sini satu akun demo dari seeder: `admin@anubis.test` / `password` |
| Tabel `profiles` | `supabase/account/001_schema.sql` | port ini memakai tabel `users` bawaan Laravel apa adanya |
| Unggah gambar produk | unggah berkas ke storage | di sini hanya kolom `image_url`; kalau kosong, kartu produk menampilkan inisial nama |
| Policy / banyak penjual | tiap penjual punya produknya | belum ada `Policy`/`Gate`: penjual mana pun yang masuk boleh mengubah produk mana pun |
| Berkas bahasa `lang/id` | teks antarmuka bahasa Indonesia | pesan Indonesia di sini ditulis langsung di Blade & aturan validasi controller, jadi folder `lang/` bawaan Laravel belum dipakai |

## Kredit

- Tampilan, copywriting, dan skema produk: [wang-vault/anubis](https://github.com/wang-vault/anubis) —
  dipakai sebagai acuan desain & skema. Repo itu **tidak menyertakan berkas LICENSE** dan pemiliknya sama
  (`wang-vault`), jadi port ini diterbitkan di bawah MIT
- Bentuk proyek Laravel: [qwerti1945/dasar_laravel](https://github.com/qwerti1945/dasar_laravel) — acuan struktur (juga tanpa LICENSE)
- Skeleton: [laravel/laravel](https://github.com/laravel/laravel) cabang `12.x` (MIT)
