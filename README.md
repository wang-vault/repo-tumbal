# Anubis Store — versi Laravel

Port **tampilan depan** [Anubis Store](https://github.com/wang-vault/anubis) (aslinya Next.js 15 +
Supabase) ke **Laravel 12 + Blade**, dengan satu halaman template yang dipakai semua halaman
(`<x-layouts.app>`), beranda `index.blade.php`, dan CRUD produk lengkap di SQLite.

Bentuk proyeknya mengikuti contoh [`qwerti1945/dasar_laravel`](https://github.com/qwerti1945/dasar_laravel):
skeleton Laravel standar + layout component `resources/views/components/layouts/app.blade.php` +
controller/model/migration/factory/seeder untuk satu entitas (di contoh: `Student`, di sini: `Product`).

> **Yang diport hanya bagian etalase.** Login di sini session-based lokal (bukan Supabase Auth),
> dan pembuatan order, checkout, chat WhatsApp, verifikasi pembayaran manual, serta notifikasi
> Telegram **tidak** ikut — semua itu tetap hidup di aplikasi aslinya. Halaman downloader juga
> cuma tiruan tampilan.

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
| Login penjual | `POST /login` berbasis session + pembatasan 5 percobaan/menit; semua rute tulis dilindungi middleware `auth` |
| Test | 27 pengujian: `AuthTest` (13 — login & hak akses), `ProductTest` (12 — katalog & CRUD), `ExampleTest` bawaan skeleton (2 — feature + unit) |

**Tidak perlu `npm install` / `npm run build`.** Layout memakai `<link rel="stylesheet">` ke CSS
statis, bukan `@vite`, jadi `php artisan serve` langsung menampilkan tampilan lengkap. (Vite +
Tailwind bawaan skeleton tetap ada dan tetap dipakai `resources/views/welcome.blade.php` kalau
kamu mau memakainya nanti.)

---

## Login & hak akses

Katalog **boleh dibaca siapa saja**; yang dilindungi hanya aksi tulis.

| Siapa | Boleh |
|---|---|
| Tamu | `/`, `/about`, `/downloader`, `/products` (hanya produk **aktif**), `/products/{id}` |
| Penjual (sudah masuk) | semua di atas + `/products/create`, `POST /products`, `/products/{id}/edit`, `PUT`, `DELETE`, dan melihat produk nonaktif di daftar kelola |

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
php artisan migrate --seed             # buat tabel + akun penjual demo + 10 produk contoh
php artisan serve                      # http://127.0.0.1:8000
```

Menjalankan test:

```bash
php artisan test                    # semua test (27)
php artisan test --filter=AuthTest     # khusus login & hak akses
php artisan test --filter=ProductTest  # khusus produk
```

Login di browser: buka <http://127.0.0.1:8000/login> dengan `admin@anubis.test` / `password`.

## Rute

| Method | URI | Nama | Middleware | Controller |
|---|---|---|---|---|
| GET | `/` | `home` | `web` | `HomeController@index` |
| GET | `/about` | `about` | `web` | `HomeController@about` |
| GET | `/downloader` | `downloader` | `web` | `HomeController@downloader` |
| GET | `/products` | `product-list` | `web` | `ProductController@index` |
| GET | `/products/{product}` | `product-show` | `web` | `ProductController@show` |
| GET | `/login` | `login` | `web, guest` | `Auth\LoginController@show` |
| POST | `/login` | `login.store` | `web, guest` | `Auth\LoginController@store` |
| POST | `/logout` | `logout` | `web, auth` | `Auth\LoginController@destroy` |
| GET | `/products/create` | `product-create` | `web, auth` | `ProductController@create` |
| POST | `/products` | `product-store` | `web, auth` | `ProductController@store` |
| GET | `/products/{product}/edit` | `product-edit` | `web, auth` | `ProductController@edit` |
| PUT | `/products/{product}` | `product-update` | `web, auth` | `ProductController@update` |
| DELETE | `/products/{product}` | `product-destroy` | `web, auth` | `ProductController@destroy` |
| GET | `/up` | — | — | health check bawaan Laravel |

Urutan pendaftaran di `routes/web.php` penting: `/products/create` dan
`/products/{product}/edit` harus didefinisikan **sebelum** `/products/{product}`,
kalau tidak kata `create`/`edit` ditangkap sebagai parameter `{product}`.

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
app/Http/Controllers/Auth/LoginController.php    login/logout + pembatasan percobaan
app/Http/Controllers/HomeController.php          beranda, tentang, downloader
app/Http/Controllers/ProductController.php       CRUD produk + pencarian
app/Models/Product.php                           scope active(), formatted_price, status_label
database/migrations/2026_09_25_000000_create_products_table.php
database/factories/ProductFactory.php            + state inactive()
database/seeders/UserSeeder.php                  akun penjual demo (admin@anubis.test)
database/seeders/ProductSeeder.php               6 produk pilihan + 4 acak
database/seeders/DatabaseSeeder.php              memanggil UserSeeder + ProductSeeder
resources/views/components/layouts/app.blade.php TEMPLATE: masthead + nav + flash + footer + {{ $slot }}
resources/views/index.blade.php                  beranda (dipakai route /)
resources/views/auth/login.blade.php             form masuk
resources/views/about.blade.php
resources/views/downloader.blade.php
resources/views/products/index.blade.php
resources/views/products/create.blade.php
resources/views/products/edit.blade.php
resources/views/products/show.blade.php
resources/views/products/partials/form.blade.php form bersama create/edit
public/css/anubis.css                            seluruh tampilan (tanpa build step)
tests/Feature/AuthTest.php                       13 pengujian login & hak akses
tests/Feature/ProductTest.php                    12 pengujian katalog, CRUD, validasi, seeder
composer.lock                                    dari Laravel 12.68.0, content-hash cocok
```

Diubah dari skeleton: `routes/web.php`, `bootstrap/app.php` (arah redirect `auth`/`guest`),
`.env.example` (`APP_NAME`, `APP_FAKER_LOCALE=id_ID`), `tests/Feature/ExampleTest.php`
(pakai `RefreshDatabase`), `README.md`.

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

Port ini memindahkan **etalase toko** (beranda, katalog, detail produk) + **login penjual** +
**CRUD produk**. Aplikasi Anubis aslinya lebih besar; bagian berikut belum ikut dipindahkan.

| Belum ada | Kondisi di Anubis asli | Catatan |
|---|---|---|
| Halaman `/testimoni` | otomatis dari pesanan berstatus `DONE` (maks 20 terbaru), hanya kolom non-sensitif, nama pembeli dipendekkan jadi "Budi S." | ikut tersendat karena sumbernya tabel `orders` yang belum diporting; bisa dibuat versi statis dulu kalau memang perlu |
| Pesanan & pembayaran | `/checkout`, `/orders`, `/orders/{code}`, `/orders/{code}/receipt`, `/pay/{code}` + tabel `orders` dan `manual_payment_settings` (manual, Stenly, WhatsApp) | di aslinya pembayaran manual lewat WhatsApp; port ini berhenti di katalog |
| Panel admin terpisah | `/admin` (dashboard), `/admin/products`, `/admin/orders`, `/admin/settings` | di sini digabung: `/products` berubah jadi daftar kelola setelah penjual masuk |
| Downloader yang berfungsi | benar-benar mengunduh dari YouTube/audio/Instagram/TikTok lewat 8 rute API | di sini hanya tiruan tampilan, sesuai kesepakatan awal |
| Registrasi, lupa/reset password, verifikasi email | ada di `/auth/*` | di sini satu akun demo dari seeder: `admin@anubis.test` / `password` |
| Tabel `profiles` | `supabase/account/001_schema.sql` | port ini memakai tabel `users` bawaan Laravel apa adanya |
| Halaman error bergaya Anubis | `not-found.tsx` | masih memakai 404/403/500 bawaan Laravel |
| Pagination katalog | daftar produk dipaginasi | `ProductController::index()` masih `->get()`; pencarian `?q=` sudah jalan |
| Unggah gambar produk | unggah berkas ke storage | di sini hanya kolom `image_url`; kalau kosong, kartu produk menampilkan inisial nama |
| Policy / banyak penjual | tiap penjual punya produknya | belum ada `Policy`/`Gate`: penjual mana pun yang masuk boleh mengubah produk mana pun |
| CI, `LICENSE`, `lang/id` | ada workflow & berkas bahasa | belum disertakan |

## Kredit

- Tampilan, copywriting, dan skema produk: [wang-vault/anubis](https://github.com/wang-vault/anubis) (MIT)
- Bentuk proyek Laravel: [qwerti1945/dasar_laravel](https://github.com/qwerti1945/dasar_laravel)
- Skeleton: [laravel/laravel](https://github.com/laravel/laravel) cabang `12.x` (MIT)
