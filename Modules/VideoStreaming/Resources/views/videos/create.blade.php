@extends('layouts.admin')
@section('page-title')
    {{ __('Upload Video') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('videostreaming.index') }}">{{ __('Video Streaming') }}</a></li>
    <li class="breadcrumb-item">{{ __('Upload Video') }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Upload New Video') }}</h5>
            </div>
            <div class="card-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    @csrf
                    
                    <!-- Video File Upload -->
                    <div class="mb-4">
                        <label class="form-label">{{ __('Video File') }} <span class="text-danger">*</span></label>
                        <div class="upload-area border border-2 border-dashed rounded p-5 text-center" id="uploadArea">
                            <input type="file" name="video" id="videoFile" accept="video/*" class="d-none" required>
                            <div class="upload-content">
                                <i class="ti ti-cloud-upload" style="font-size: 4rem; color: #6c757d;"></i>
                                <h5 class="mt-3">{{ __('Drag & drop your video here') }}</h5>
                                <p class="text-muted">{{ __('or click to browse') }}</p>
                                <p class="small text-muted">{{ __('Supported formats: MP4, MOV, AVI, MKV, WebM') }}</p>
                                <p class="small text-muted">{{ __('Maximum file size: 5GB') }}</p>
                            </div>
                            <div class="upload-preview d-none">
                                <i class="ti ti-video" style="font-size: 3rem; color: #0d6efd;"></i>
                                <h5 class="mt-2 file-name"></h5>
                                <p class="text-muted file-size"></p>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="removeFile">
                                    <i class="ti ti-x me-1"></i>{{ __('Remove') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-4 d-none" id="progressContainer">
                        <label class="form-label">{{ __('Upload Progress') }}</label>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%;" id="uploadProgress">0%</div>
                        </div>
                        <p class="small text-muted mt-2" id="uploadStatus">{{ __('Uploading...') }}</p>
                    </div>

                    <!-- Video Details -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control" placeholder="{{ __('Enter video title') }}">
                        <small class="text-muted">{{ __('Leave empty to use filename') }}</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="{{ __('Enter video description (optional)') }}"></textarea>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">{{ __('Protection Settings') }}</h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="domain_lock_enabled" 
                                           id="domainLock" checked>
                                    <label class="form-check-label" for="domainLock">
                                        {{ __('Enable Domain Lock') }}
                                    </label>
                                </div>
                                <small class="text-muted">{{ __('Video can only be played on specified domains') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="watermark_enabled" 
                                           id="watermark" checked>
                                    <label class="form-check-label" for="watermark">
                                        {{ __('Enable Watermark') }}
                                    </label>
                                </div>
                                <small class="text-muted">{{ __('Display watermark on video') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="watermarkTextContainer">
                        <label class="form-label">{{ __('Watermark Text') }}</label>
                        <input type="text" name="watermark_text" class="form-control" 
                               placeholder="{{ __('Your brand name or website') }}">
                    </div>

                    <div class="mb-3" id="domainsContainer">
                        <label class="form-label">{{ __('Allowed Domains') }}</label>
                        <textarea name="allowed_domains_text" class="form-control" rows="2" 
                                  placeholder="example.com&#10;subdomain.example.com"></textarea>
                        <small class="text-muted">{{ __('One domain per line. Leave empty to use global settings.') }}</small>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">{{ __('Password Protection') }}</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="password_protected" 
                                           id="passwordProtected">
                                    <label class="form-check-label" for="passwordProtected">
                                        {{ __('Enable Password Protection') }}
                                    </label>
                                </div>
                                <small class="text-muted">{{ __('Viewers need password to watch') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6" id="passwordContainer" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Video Password') }}</label>
                                <input type="text" name="password" class="form-control" 
                                       placeholder="{{ __('Enter password') }}">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">{{ __('Player Settings') }}</h6>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_play" id="autoPlay">
                                    <label class="form-check-label" for="autoPlay">{{ __('Auto Play') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="loop_video" id="loopVideo">
                                    <label class="form-check-label" for="loopVideo">{{ __('Loop Video') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_controls" id="showControls" checked>
                                    <label class="form-check-label" for="showControls">{{ __('Show Controls') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="download_enabled" id="downloadEnabled">
                                    <label class="form-check-label" for="downloadEnabled">{{ __('Allow Download') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="share_enabled" id="shareEnabled" checked>
                                    <label class="form-check-label" for="shareEnabled">{{ __('Allow Share') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Watermark Position') }}</label>
                                <select name="watermark_position" class="form-select">
                                    <option value="top-right">{{ __('Top Right') }}</option>
                                    <option value="top-left">{{ __('Top Left') }}</option>
                                    <option value="bottom-right">{{ __('Bottom Right') }}</option>
                                    <option value="bottom-left">{{ __('Bottom Left') }}</option>
                                    <option value="center">{{ __('Center') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">{{ __('Access Limits') }}</h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Maximum Views') }}</label>
                                <input type="number" name="max_views" class="form-control" 
                                       placeholder="{{ __('Leave empty for unlimited') }}" min="1">
                                <small class="text-muted">{{ __('Video will be disabled after this number of views') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Expiration Date') }}</label>
                                <input type="datetime-local" name="expires_at" class="form-control">
                                <small class="text-muted">{{ __('Leave empty for no expiration') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('videostreaming.index') }}" class="btn btn-secondary">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="ti ti-upload me-1"></i>{{ __('Upload Video') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const videoFile = document.getElementById('videoFile');
    const uploadContent = uploadArea.querySelector('.upload-content');
    const uploadPreview = uploadArea.querySelector('.upload-preview');
    const removeFileBtn = document.getElementById('removeFile');
    const uploadForm = document.getElementById('uploadForm');
    const progressContainer = document.getElementById('progressContainer');
    const uploadProgress = document.getElementById('uploadProgress');
    const uploadStatus = document.getElementById('uploadStatus');
    const uploadBtn = document.getElementById('uploadBtn');

    // Drag and drop
    uploadArea.addEventListener('click', () => videoFile.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('border-primary', 'bg-light');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('border-primary', 'bg-light');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('border-primary', 'bg-light');
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('video/')) {
            videoFile.files = files;
            showFilePreview(files[0]);
        }
    });

    videoFile.addEventListener('change', function() {
        if (this.files.length > 0) {
            showFilePreview(this.files[0]);
        }
    });

    function showFilePreview(file) {
        uploadContent.classList.add('d-none');
        uploadPreview.classList.remove('d-none');
        uploadPreview.querySelector('.file-name').textContent = file.name;
        uploadPreview.querySelector('.file-size').textContent = formatFileSize(file.size);
    }

    removeFileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        videoFile.value = '';
        uploadContent.classList.remove('d-none');
        uploadPreview.classList.add('d-none');
    });

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Form submission
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!videoFile.files.length) {
            toastr.error('{{ __("Please select a video file") }}');
            return;
        }

        const formData = new FormData(this);
        
        // Convert domains text to array
        const domainsText = formData.get('allowed_domains_text');
        if (domainsText) {
            const domains = domainsText.split('\n').map(d => d.trim()).filter(d => d);
            formData.delete('allowed_domains_text');
            domains.forEach(d => formData.append('allowed_domains[]', d));
        }

        uploadBtn.disabled = true;
        progressContainer.classList.remove('d-none');

        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                uploadProgress.style.width = percent + '%';
                uploadProgress.textContent = percent + '%';
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    uploadStatus.textContent = '{{ __("Upload complete! Processing video...") }}';
                    uploadProgress.classList.remove('progress-bar-animated');
                    uploadProgress.classList.add('bg-success');
                    
                    setTimeout(() => {
                        window.location.href = '{{ route("videostreaming.show", "") }}/' + response.video.uuid;
                    }, 1500);
                } else {
                    toastr.error(response.message);
                    resetUpload();
                }
            } else {
                toastr.error('{{ __("Upload failed. Please try again.") }}');
                resetUpload();
            }
        });

        xhr.addEventListener('error', function() {
            toastr.error('{{ __("Upload failed. Please try again.") }}');
            resetUpload();
        });

        xhr.open('POST', '{{ route("videostreaming.store") }}');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(formData);
    });

    function resetUpload() {
        uploadBtn.disabled = false;
        progressContainer.classList.add('d-none');
        uploadProgress.style.width = '0%';
        uploadProgress.textContent = '0%';
        uploadProgress.classList.add('progress-bar-animated');
        uploadProgress.classList.remove('bg-success');
    }

    // Toggle watermark text field
    document.getElementById('watermark').addEventListener('change', function() {
        document.getElementById('watermarkTextContainer').style.display = this.checked ? 'block' : 'none';
    });

    // Toggle domains field
    document.getElementById('domainLock').addEventListener('change', function() {
        document.getElementById('domainsContainer').style.display = this.checked ? 'block' : 'none';
    });

    // Toggle password field
    document.getElementById('passwordProtected').addEventListener('change', function() {
        document.getElementById('passwordContainer').style.display = this.checked ? 'block' : 'none';
    });
});
</script>
@endpush

@push('styles')
<style>
.upload-area {
    cursor: pointer;
    transition: all 0.3s ease;
}
.upload-area:hover {
    border-color: var(--bs-primary) !important;
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}
</style>
@endpush
