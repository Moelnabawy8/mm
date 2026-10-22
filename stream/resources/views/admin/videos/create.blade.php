@extends('layouts.admin')

@section('title', 'Upload Video')
@section('page-title', 'Upload Video')

@section('content')
<div class="max-w-2xl" x-data="videoUploader()">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <!-- Step 1: Video Details -->
        <div x-show="step === 1">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Video Details</h3>

            <div class="mb-5">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" id="title" x-model="form.title" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
            </div>

            <div class="mb-5">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" x-model="form.description" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"></textarea>
            </div>

            <div class="mb-5">
                <label for="visibility" class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
                <select id="visibility" x-model="form.visibility"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </div>

            <button @click="nextStep()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Next: Upload File
            </button>
        </div>

        <!-- Step 2: File Upload -->
        <div x-show="step === 2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload Video File</h3>

            <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center mb-4"
                 x-show="!uploading && !uploadComplete"
                 @dragover.prevent="dragover = true"
                 @dragleave="dragover = false"
                 @drop.prevent="handleDrop($event)"
                 :class="dragover ? 'border-indigo-500 bg-indigo-50' : ''">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-600 mb-2">Drag & drop your video file here</p>
                <p class="text-sm text-gray-400 mb-4">or</p>
                <label class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition cursor-pointer">
                    Browse Files
                    <input type="file" accept="video/*" @change="handleFile($event)" class="hidden">
                </label>
                <p class="text-xs text-gray-400 mt-3">Supports MP4, MOV, AVI, MKV. Max 10GB.</p>
            </div>

            <!-- Upload Progress -->
            <div x-show="uploading" class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700" x-text="fileName"></span>
                    <span class="text-sm text-gray-500" x-text="uploadProgress + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-indigo-600 h-3 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2" x-text="uploadStatus"></p>
            </div>

            <!-- Upload Complete -->
            <div x-show="uploadComplete" class="text-center py-8">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Upload Complete!</h4>
                <p class="text-sm text-gray-500 mb-4">Video is now being processed. This may take a few minutes.</p>
                <a href="{{ route('admin.videos.index') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Back to Videos
                </a>
            </div>

            <!-- Error -->
            <div x-show="uploadError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm" x-text="uploadError"></div>

            <button x-show="!uploading && !uploadComplete" @click="step = 1" class="text-gray-500 hover:text-gray-700 text-sm mt-4">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function videoUploader() {
    return {
        step: 1,
        dragover: false,
        uploading: false,
        uploadComplete: false,
        uploadProgress: 0,
        uploadStatus: '',
        uploadError: '',
        fileName: '',
        videoId: null,
        form: {
            title: '',
            description: '',
            visibility: 'public',
        },

        async nextStep() {
            if (!this.form.title) {
                alert('Please enter a video title');
                return;
            }

            try {
                const response = await fetch('{{ route("admin.videos.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });

                const data = await response.json();
                if (data.success) {
                    this.videoId = data.video_id;
                    this.step = 2;
                } else {
                    alert('Failed to create video record');
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },

        handleDrop(event) {
            this.dragover = false;
            const file = event.dataTransfer.files[0];
            if (file) this.startUpload(file);
        },

        handleFile(event) {
            const file = event.target.files[0];
            if (file) this.startUpload(file);
        },

        async startUpload(file) {
            this.uploading = true;
            this.uploadError = '';
            this.fileName = file.name;

            const chunkSize = 10 * 1024 * 1024; // 10MB
            const totalChunks = Math.ceil(file.size / chunkSize);

            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('chunk', chunk, file.name);
                formData.append('chunk_index', i);
                formData.append('total_chunks', totalChunks);

                try {
                    this.uploadStatus = `Uploading chunk ${i + 1} of ${totalChunks}...`;

                    const response = await fetch(`/admin/videos/${this.videoId}/upload-chunk`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    if (!response.ok) {
                        this.uploadError = `Upload failed at chunk ${i + 1}: server returned ${response.status} ${response.statusText}`;
                        this.uploading = false;
                        return;
                    }

                    const data = await response.json();
                    if (!data.success) {
                        this.uploadError = 'Upload failed at chunk ' + (i + 1) + (data.message ? ': ' + data.message : '');
                        this.uploading = false;
                        return;
                    }

                    this.uploadProgress = Math.round(((i + 1) / totalChunks) * 100);

                    if (data.status === 'processing') {
                        this.uploadComplete = true;
                        this.uploading = false;
                    }
                } catch (e) {
                    this.uploadError = 'Upload error: ' + e.message;
                    this.uploading = false;
                    return;
                }
            }
        }
    };
}
</script>
@endpush
@endsection
