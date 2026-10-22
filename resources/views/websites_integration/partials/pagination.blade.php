@if (($pages ?? 1) > 1)
    @php
        $extra = $extra ?? [];
        $window = 2;
        $start = max(1, $currentPage - $window);
        $end = min($pages, $currentPage + $window);
    @endphp
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm justify-content-end mt-3 mb-0 flex-wrap">
            <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                <a class="page-link" href="{{ route($route, array_merge($extra, ['page' => max(1, $currentPage - 1)])) }}">
                    <i class="ti ti-chevron-left"></i>
                </a>
            </li>
            @if ($start > 1)
                <li class="page-item"><a class="page-link" href="{{ route($route, array_merge($extra, ['page' => 1])) }}">1</a></li>
                @if ($start > 2)<li class="page-item disabled"><span class="page-link">…</span></li>@endif
            @endif
            @for ($p = $start; $p <= $end; $p++)
                <li class="page-item {{ $p == $currentPage ? 'active' : '' }}">
                    <a class="page-link" href="{{ route($route, array_merge($extra, ['page' => $p])) }}">{{ $p }}</a>
                </li>
            @endfor
            @if ($end < $pages)
                @if ($end < $pages - 1)<li class="page-item disabled"><span class="page-link">…</span></li>@endif
                <li class="page-item"><a class="page-link" href="{{ route($route, array_merge($extra, ['page' => $pages])) }}">{{ $pages }}</a></li>
            @endif
            <li class="page-item {{ $currentPage >= $pages ? 'disabled' : '' }}">
                <a class="page-link" href="{{ route($route, array_merge($extra, ['page' => min($pages, $currentPage + 1)])) }}">
                    <i class="ti ti-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
@endif
