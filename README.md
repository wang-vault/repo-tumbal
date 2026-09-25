# Anubis Store — versi Laravel

Port **tampilan depan** [Anubis Store](https://github.com/wang-vault/anubis) (aslinya Next.js 15 +
Supabase) ke **Laravel 12 + Blade**, dengan satu halaman template yang dipakai semua halaman
(`<x-layouts.app>`), beranda `index.blade.php`, dan CRUD produk lengkap di SQLite.

Bentuk proyeknya mengikuti contoh [`qwerti1945/dasar_laravel`](https://github.com/qwerti1945/dasar_laravel):
skeleton Laravel standar + layout component `resources/views/components/layouts/app.blade.php` +
controller/model/migration/factory/seeder untuk satu entitas (di contoh: `Student`, di sini: `Product`).

> **Yang diport hanya bagian etalase.** Autentikasi Supabase, pembuatan order, checkout, chat
> WhatsApp, verifikasi pembayaran manual, dan notifikasi Telegram **tidak** ikut — semua itu tetap
> hidup di aplikasi aslinya. Halaman downloader juga cuma tiruan tampilan.

---

## Yang ada di sini

| Bagian | Isi |
|---|---|
| Beranda `/` | Hero "Belanja gampang, *kabar* pembayaran datang cepat", 3 langkah cara kerja, teaser downloader, 6 produk terbaru |
| Katalog `/products` | Daftar semua produk + pencarian `?q=...` (form GET, jalan tanpa JavaScript) |
| CRUD produk | Tambah, lihat detail, ubah, hapus — dengan validasi & pesan flash |
| Tentang `/about` | Penjelasan alur transfer manual via WhatsApp |
| Downloader `/downloader` | Halaman statis 3 platform (TikTok, YouTube, Instagram) |
| Tampilan | CSS statis `public/css/anubis.css` — gaya koran: kertas hangat, tinta, aksen oxblood, sudut tajam, bayangan offset |
| Test | `tests/Feature/ProductTest.php` (12 pengujian: beranda, pencarian, CRUD, validasi, seeder) |

**Tidak perlu `npm install` / `npm run build`.** Layout memakai `<link rel="stylesheet">` ke CSS
statis, bukan `@vite`, jadi `php artisan serve` langsung menampilkan tampilan lengkap. (Vite +
Tailwind bawaan skeleton tetap ada dan tetap dipakai `resources/views/welcome.blade.php` kalau
kamu mau memakainya nanti.)

---

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
php artisan migrate --seed             # buat tabel products + isi 10 produk contoh
php artisan serve                      # http://127.0.0.1:8000
```

Menjalankan test:

```bash
php artisan test                       # semua test
php artisan test --filter=ProductTest  # khusus produk
```

## Rute

| Method | URI | Nama | Controller |
|---|---|---|---|
| GET | `/` | `home` | `HomeController@index` |
| GET | `/about` | `about` | `HomeController@about` |
| GET | `/downloader` | `downloader` | `HomeController@downloader` |
| GET | `/products` | `product-list` | `ProductController@index` |
| GET | `/products/create` | `product-create` | `ProductController@create` |
| POST | `/products` | `product-store` | `ProductController@store` |
| GET | `/products/{product}` | `product-show` | `ProductController@show` |
| GET | `/products/{product}/edit` | `product-edit` | `ProductController@edit` |
| PUT | `/products/{product}` | `product-update` | `ProductController@update` |
| DELETE | `/products/{product}` | `product-destroy` | `ProductController@destroy` |
| GET | `/up` | — | health check bawaan Laravel |

Penamaan rute (`product-list`, `product-create`, …) sengaja meniru gaya `student-*` di repo contoh.

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

## Berkas yang ditambahkan / diubah

Ditambahkan di atas skeleton Laravel standar:

```
app/Http/Controllers/HomeController.php          beranda, tentang, downloader
app/Http/Controllers/ProductController.php       CRUD produk + pencarian
app/Models/Product.php                           scope active(), formatted_price, status_label
database/migrations/2026_09_25_000000_create_products_table.php
database/factories/ProductFactory.php            + state inactive()
database/seeders/ProductSeeder.php               6 produk pilihan + 4 acak
database/seeders/DatabaseSeeder.php              memanggil ProductSeeder
resources/views/components/layouts/app.blade.php TEMPLATE: masthead + nav + flash + footer + {{ $slot }}
resources/views/index.blade.php                  beranda (dipakai route /)
resources/views/about.blade.php
resources/views/downloader.blade.php
resources/views/products/index.blade.php
resources/views/products/create.blade.php
resources/views/products/edit.blade.php
resources/views/products/show.blade.php
resources/views/products/partials/form.blade.php form bersama create/edit
public/css/anubis.css                            seluruh tampilan (tanpa build step)
tests/Feature/ProductTest.php
composer.lock                                    dari Laravel 12.68.0, content-hash cocok
```

Diubah dari skeleton: `routes/web.php`, `.env.example` (`APP_NAME`, `APP_FAKER_LOCALE=id_ID`),
`README.md`.

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

## Kredit

- Tampilan, copywriting, dan skema produk: [wang-vault/anubis](https://github.com/wang-vault/anubis) (MIT)
- Bentuk proyek Laravel: [qwerti1945/dasar_laravel](https://github.com/qwerti1945/dasar_laravel)
- Skeleton: [laravel/laravel](https://github.com/laravel/laravel) cabang `12.x` (MIT)
