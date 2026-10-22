@extends('layouts.admin')

@section('title', 'Allowed Domains')
@section('page-title', 'Allowed Domains')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Add Domain -->
    <div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Add Global Domain</h3>
            <p class="text-xs text-gray-500 mb-4">Global domains apply to all videos. Per-video domains can be managed from each video's detail page.</p>
            <form action="{{ route('admin.domains.store') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="domain" class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                    <input type="text" id="domain" name="domain" value="{{ old('domain') }}" required placeholder="example.com"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm">
                    <p class="text-xs text-gray-400 mt-1">Enter domain without http:// or https://</p>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-plus mr-1"></i> Add Domain
                </button>
            </form>
        </div>
    </div>

    <!-- Domain List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">Whitelisted Domains ({{ $domains->total() }})</h3>
                <p class="text-xs text-gray-500 mt-1">Global domains that apply to all videos</p>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($domains as $domain)
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-globe text-green-600 text-xs"></i>
                            </div>
                            <div>
                                <span class="font-medium text-gray-800">{{ $domain->domain }}</span>
                                @if($domain->video)
                                    <p class="text-xs text-gray-500">Video: {{ $domain->video->title }}</p>
                                @else
                                    <p class="text-xs text-indigo-500">Global</p>
                                @endif
                            </div>
                        </div>
                        <form action="{{ route('admin.domains.destroy', $domain) }}" method="POST" onsubmit="return confirm('Remove this domain?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-gray-400 hover:text-red-600 transition text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-globe text-3xl mb-3"></i>
                        <p>No domains whitelisted. Embeds will only work on this domain.</p>
                    </div>
                @endforelse
            </div>
            <div class="px-6 py-4 border-t border-gray-200">{{ $domains->links() }}</div>
        </div>
    </div>
</div>
@endsection
