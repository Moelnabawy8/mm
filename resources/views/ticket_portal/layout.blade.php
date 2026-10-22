<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $settings->localized_title ?? __('Support Portal'))</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary-color: {{ $settings->portal_primary_color ?? '#6366f1' }};
            --secondary-color: {{ $settings->portal_secondary_color ?? '#8b5cf6' }};
        }

        * {
            font-family: {{ app()->getLocale() == 'ar' ? "'Tajawal', sans-serif" : "'Inter', sans-serif" }};
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
        }

        .portal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 2rem 0;
            color: white;
            margin-bottom: 2rem;
        }

        .portal-logo {
            max-height: 60px;
            margin-bottom: 1rem;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid #f0f0f0;
            padding: 1.25rem 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .required-star {
            color: #ef4444;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .priority-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background: transparent;
        }

        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
        }

        .alert {
            border-radius: 12px;
            border: none;
        }

        .reply-card {
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .reply-card.staff {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border-left: 4px solid var(--primary-color);
        }

        .reply-card.customer {
            background: #f8fafc;
            border-left: 4px solid #94a3b8;
        }

        .loading-spinner {
            display: none;
        }

        .loading-spinner.active {
            display: inline-block;
        }

        @media (max-width: 768px) {
            .portal-header {
                padding: 1.5rem 0;
            }

            .card {
                border-radius: 12px;
            }

            .btn-primary {
                width: 100%;
            }
        }
    </style>

    @yield('styles')
</head>

<body>
    <header class="portal-header">
        <div class="container">
            <div class="text-center">
                @php
                    $setting = \App\Models\Utility::settings();
                    $logo = \App\Models\Utility::get_file('uploads/logo');
                    $company_logos =
                        isset($setting['company_logo_light']) && !empty($setting['company_logo_light'])
                            ? $setting['company_logo_light']
                            : 'logo-dark.png';
                @endphp
                <img src="{{ $logo . '/' . $company_logos }}" alt="Logo" class="portal-logo">
                <h1 class="h3 mb-2">{{ $settings->localized_title }}</h1>
                @if ($settings->localized_description)
                    <p class="mb-0 opacity-75">{{ $settings->localized_description }}</p>
                @endif
            </div>
        </div>
    </header>

    <main class="container pb-5">
        @yield('content')
    </main>

    <footer class="py-4 text-center text-muted">
        <div class="container">
            <small>&copy; {{ date('Y') }} {{ $settings->localized_title }}.
                {{ __('All rights reserved.') }}</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function showLoading(btn) {
            btn.disabled = true;
            btn.querySelector('.loading-spinner')?.classList.add('active');
            btn.querySelector('.btn-text')?.classList.add('d-none');
        }

        function hideLoading(btn) {
            btn.disabled = false;
            btn.querySelector('.loading-spinner')?.classList.remove('active');
            btn.querySelector('.btn-text')?.classList.remove('d-none');
        }

        function showAlert(container, type, message) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.prepend(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>

    @yield('scripts')
</body>

</html>
