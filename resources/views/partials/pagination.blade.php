{{--
    Tampilan pagination sendiri, dipakai untuk semua pemanggilan paginate().

    Didaftarkan di AppServiceProvider::configurePagination() lewat
    Paginator::defaultView('partials.pagination'), jadi di view cukup menulis
    {{ $products->links() }} tanpa menyebut nama view-nya.

    Alasan tidak memakai bawaan Laravel: view pagination bawaan penuh class
    Tailwind, sedangkan proyek ini tidak menjalankan `npm run build`.

    Variabel yang dioper Laravel ke view ini: $paginator dan $elements.
--}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pindah halaman">
        @if ($paginator->onFirstPage())
            <span class="pager-btn pager-btn-disabled" aria-hidden="true">← Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="pager-btn" rel="prev">← Sebelumnya</a>
        @endif

        <span class="pager-numbers">
            @foreach ($elements as $element)
                {{-- Titik-titik pemisah ("...") dari sliding window Laravel. --}}
                @if (is_string($element))
                    <span class="pager-gap" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="pager-btn pager-btn-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pager-btn">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="pager-btn" rel="next">Berikutnya →</a>
        @else
            <span class="pager-btn pager-btn-disabled" aria-hidden="true">Berikutnya →</span>
        @endif

        <span class="pager-meta">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} ·
            menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            dari {{ $paginator->total() }} produk
        </span>
    </nav>
@endif
