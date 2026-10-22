@extends('layouts.admin')
@section('page-title')
    {{ $sheet->name }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('google-sheet.index') }}">{{ __('Sheets') }}</a></li>
    <li class="breadcrumb-item">{{ $sheet->name }}</li>
@endsection
@push('css-page')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/styles/handsontable.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/styles/ht-theme-main.min.css">
@endpush
@section('content')
    <div
        style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end; margin-bottom: 1rem; margin-top: 0.5rem;">
        <button id="importBtn"
            style="
            background-color: #e5e7eb;
            color: #1f2937;
            padding: 0.5rem 1rem;
            font-weight: bold;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
        ">
            <i class="ti ti-download"></i> {{ __('Import Excel') }}
        </button>
        <input type="file" id="importFile" style="display:none" accept=".xlsx, .xls, .csv" />
        <button id="exportBtn"
            style="
            background-color: #e5e7eb;
            color: #1f2937;
            padding: 0.5rem 1rem;
            font-weight: bold;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
        ">
            <i class="ti ti-upload"></i> {{ __('Export Excel') }}
        </button>
    </div>

    <button id="saveBtn"
        style="
        background-color: #e5e7eb;
        color: #1f2937;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        margin-bottom: 1rem;
        cursor: pointer;
    ">
        <i class="ti ti-device-floppy"></i> {{ __('Save') }}
    </button>

    <div id="example" style="width:100%; height:auto; overflow-x:auto;"></div>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
    <script>
        let data = @json($sheet->data);
        if (!data || data.length === 0) {
            data = Array.from({
                length: 10
            }, () => Array(10).fill(""));
        }
        window.sheetData = data;
        window.saveUrl = "{{ route('google-sheet.save', $sheet->id) }}";
        window.csrfToken = '{{ csrf_token() }}';
    </script>
    <script src="{{ asset('js/google-sheet.js') }}"></script>
@endpush
