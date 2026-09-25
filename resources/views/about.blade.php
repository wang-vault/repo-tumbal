<x-layouts.app title="Cara Bayar">
    <div class="container-x stack">

        <section class="front-page-hero front-page-hero--slim">
            <div class="front-page-copy">
                <p class="eyebrow">Tentang toko · Satu metode bayar</p>
                <h1 class="front-page-title">Transfer manual, <em>dikoordinasikan</em> lewat WhatsApp.</h1>
                <p class="front-page-deck">
                    Toko ini sengaja hanya punya satu cara bayar. Tidak ada payment gateway, tidak ada
                    QRIS otomatis, tidak ada webhook — semua detail pembayaran dikirim penjual langsung
                    di dalam chat, jadi selalu yang terbaru dan tidak pernah basi.
                </p>
            </div>
        </section>

        <section>
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Alur pembayaran</p>
                    <h2 class="section-title">Dari pilih barang sampai LUNAS.</h2>
                </div>
            </div>

            <div class="steps-grid">
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">01</span>
                    <div>
                        <h3 class="step-title">Checkout pesanan</h3>
                        <p class="step-copy">
                            Pilih produk, isi jumlah, lalu buat pesanan. Sistem menyimpan snapshot nama
                            produk dan harga satuan supaya perubahan katalog tidak mengubah histori.
                        </p>
                    </div>
                </article>
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">02</span>
                    <div>
                        <h3 class="step-title">Chat penjual di WhatsApp</h3>
                        <p class="step-copy">
                            Tombol chat sudah berisi kode order dan nominal. Penjual membalas dengan
                            detail pembayaran: QRIS statis, nomor rekening, atau e-wallet.
                        </p>
                    </div>
                </article>
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">03</span>
                    <div>
                        <h3 class="step-title">Transfer + kode unik</h3>
                        <p class="step-copy">
                            Nominal ditambahkan kode unik beberapa rupiah agar mutasi mudah dicocokkan.
                            Setelah transfer, tekan "Saya sudah transfer".
                        </p>
                    </div>
                </article>
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">04</span>
                    <div>
                        <h3 class="step-title">Penjual verifikasi mutasi</h3>
                        <p class="step-copy">
                            Penjual mengecek mutasi rekening, lalu menyetujui klaim. Status berubah jadi
                            LUNAS, penjual dapat notifikasi Telegram, barang diproses, dan pesanan
                            ditandai Selesai.
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <section class="tool-teaser">
            <div>
                <p class="section-kicker">Catatan porting</p>
                <h2 class="tool-teaser-title">Versi Laravel ini hanya bagian depan</h2>
                <p class="tool-teaser-text">
                    Repo ini adalah port tampilan Anubis Store ke Laravel: halaman depan, katalog, dan
                    CRUD produk dengan database SQLite. Bagian autentikasi (Supabase Auth), pembuatan
                    order, chat WhatsApp, dan notifikasi Telegram tidak ikut diport — semuanya tetap ada
                    di aplikasi aslinya yang berbasis Next.js.
                </p>
                <ul class="tool-teaser-list">
                    <li>Halaman depan</li>
                    <li>Katalog</li>
                    <li>CRUD produk</li>
                </ul>
            </div>
            <div class="tool-teaser-action">
                <a href="https://github.com/wang-vault/anubis" class="btn-primary" rel="noopener">Repo Asli →</a>
                <span class="tool-teaser-note">wang-vault/anubis · Next.js 15</span>
            </div>
        </section>
    </div>
</x-layouts.app>
