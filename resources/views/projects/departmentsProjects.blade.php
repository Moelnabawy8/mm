@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Departments') }}
@endsection
@push('script-page')
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Departments') }}</li>
@endsection

@section('content')
    <style>
        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
            padding: 1.25rem 0;
        }

        .department-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .department-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #145388 0%, #1a6ba8 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .department-card:hover::before {
            transform: scaleX(1);
        }

        .department-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(20, 83, 136, 0.15);
            border-color: #145388;
        }

        .department-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #145388;
            margin-bottom: 1.25rem;
            line-height: 1.3;
            padding: 0.875rem 1.125rem;
            border: 2px solid #145388;
            border-radius: 8px;
            background: rgba(20, 83, 136, 0.05);
            text-align: center;
            transition: all 0.3s ease;
        }

        .department-card:hover .department-name {
            background: rgba(20, 83, 136, 0.1);
            border-color: #1a6ba8;
        }

        .department-btn {
            width: 100%;
            background: linear-gradient(135deg, #145388 0%, #1a6ba8 100%);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .department-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .department-btn:hover::before {
            left: 100%;
        }

        .department-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 83, 136, 0.4);
        }

        .department-btn:active {
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .departments-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }

            .department-card {
                padding: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .departments-grid {
                grid-template-columns: 1fr;
            }

            .department-name {
                font-size: 1.125rem;
                padding: 0.75rem 1rem;
            }

            .department-btn {
                font-size: 0.875rem;
                padding: 0.625rem 1rem;
            }
        }
    </style>

    <div class="departments-grid">
        @foreach ($departments as $element)
            <div class="department-card">
                <h3 class="department-name">{{ $element['name'] }}</h3>

                <a href="{{ route('departmentProjects', $element['name']) }}">
                    <button class="department-btn">
                        <i class="ti ti-arrow-right me-2"></i>
                        Open Projects
                    </button>
                </a>
            </div>
        @endforeach
    </div>
@endsection
