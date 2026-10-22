@extends('layouts.admin')
@section('page-title')
    {{ __('Sheets') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Sheets') }}</li>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: "{{ __('Are you sure?') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: "{{ __('Delete') }}"
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm' + id).submit();
                }
            });
        }
    </script>
@endpush
@section('content')
    <form action="{{ route('google-sheet.create') }}" method="POST"
        style="margin-top: 0.5rem; margin-bottom: 1rem; display: flex; gap: 0.5rem;">
        @csrf
        <input type="text" name="name" placeholder="{{ __('Sheet name') }}" required
            style="
                flex: 1;
                border: 1px solid #d1d5db;
                border-radius: 0.375rem;
                padding: 0.5rem 0.75rem;
                outline: none;
                box-shadow: inset 0 0 0 1px #9ca3af;
            ">
        <button type="submit"
            style="
                background-color: #1f2937;
                color: #ffffff;
                font-weight: 500;
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 0.375rem;
                display: flex;
                align-items: center;
                gap: 0.25rem;
                cursor: pointer;
                transition: all 0.2s ease;
            "
            onmouseover="this.style.backgroundColor='#111827';" onmouseout="this.style.backgroundColor='#1f2937';">
            <i class="ti ti-plus"></i> {{ __('Create') }}
        </button>
    </form>

    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem;">
        @foreach ($sheets as $sheet)
            <div style="
                min-width: 300px;
                flex: 1 1 300px;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                transition: all 0.2s ease;
                padding: 0.75rem;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                background-color: #ffffff;
            "
                onmouseover="this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)';"
                onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)';">
                <div>
                    <h2
                        style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $sheet->name }}
                    </h2>
                    <p style="font-size: 0.875rem; color: #6b7280;">
                        {{ __('Created') }}: {{ $sheet->created_at->diffForHumans() }}
                    </p>
                </div>

                <div
                    style="margin-top: 0.75rem; display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem;">
                    <a href="{{ route('google-sheet.show', $sheet->id) }}"
                        style="
                        background-color: #f3f4f6;
                        color: #1f2937;
                        padding: 0.25rem 0.75rem;
                        border-radius: 0.25rem;
                        font-size: 0.875rem;
                        display: flex;
                        align-items: center;
                        gap: 0.25rem;
                        text-decoration: none;
                        transition: all 0.2s ease;
                    "
                        onmouseover="this.style.backgroundColor='#e5e7eb';"
                        onmouseout="this.style.backgroundColor='#f3f4f6';">
                        <i class="ti ti-arrow-right"></i> {{ __('Open') }}
                    </a>

                    <form id="deleteForm{{ $sheet->id }}" action="{{ route('google-sheet.delete', $sheet->id) }}"
                        method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" onclick="confirmDelete({{ $sheet->id }})"
                            style="
                            background: none;
                            border: none;
                            color: #ef4444;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            gap: 0.25rem;
                            font-size: 0.875rem;
                        ">
                            <i class="ti ti-trash"></i> {{ __('Delete') }}
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    @if (count($sheets) == 0)
        <div style="text-align: center; color: #9ca3af; font-weight: 500; margin: 2.5rem 0;">
            {{ __('No sheets found.') }}
        </div>
    @endif
@endsection
