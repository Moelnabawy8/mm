@extends('ticket_portal.layout')

@section('title', __('Track Ticket') . ' - ' . $settings->localized_title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <ul class="nav nav-tabs mb-4 justify-content-center">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('ticket-portal.show', $settings->portal_slug) }}">
                        <i class="bi bi-plus-circle me-2"></i>{{ __('Create Ticket') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('ticket-portal.track', $settings->portal_slug) }}">
                        <i class="bi bi-search me-2"></i>{{ __('Track Ticket') }}
                    </a>
                </li>
            </ul>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-search me-2"></i>{{ __('Track Your Ticket') }}</h5>
                </div>
                <div class="card-body p-4">
                    <div id="alertContainer"></div>

                    <form id="trackForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ticket_code" class="form-label">{{ __('Ticket Code') }}</label>
                                <input type="text" class="form-control" id="ticket_code" name="ticket_code"
                                    placeholder="{{ __('e.g., TKT-ABC123') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">{{ __('Email Address') }}</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="{{ __('Your email address') }}">
                            </div>
                        </div>
                        <p class="text-muted small mb-3">
                            {{ __('Enter your ticket code or email address to find your tickets.') }}</p>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm loading-spinner me-2" role="status"></span>
                                <span class="btn-text"><i class="bi bi-search me-2"></i>{{ __('Search Tickets') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="resultsContainer" class="mt-4 d-none">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>{{ __('Your Tickets') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="px-4">{{ __('Ticket Code') }}</th>
                                        <th>{{ __('Subject') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Priority') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="ticketsList">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="noResultsContainer" class="mt-4 d-none">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">{{ __('No Tickets Found') }}</h5>
                        <p class="text-muted">{{ __('We could not find any tickets matching your search criteria.') }}</p>
                        <a href="{{ route('ticket-portal.show', $settings->portal_slug) }}" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-circle me-2"></i>{{ __('Create New Ticket') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('trackForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            const resultsContainer = document.getElementById('resultsContainer');
            const noResultsContainer = document.getElementById('noResultsContainer');
            const ticketsList = document.getElementById('ticketsList');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const ticketCode = document.getElementById('ticket_code').value;
                const email = document.getElementById('email').value;

                if (!ticketCode && !email) {
                    showAlert(document.getElementById('alertContainer'), 'warning',
                        '{{ __('Please enter a ticket code or email address.') }}');
                    return;
                }

                showLoading(submitBtn);
                resultsContainer.classList.add('d-none');
                noResultsContainer.classList.add('d-none');

                try {
                    const formData = new FormData(form);

                    const response = await fetch(
                        '{{ route('ticket-portal.track', $settings->portal_slug) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        if (data.tickets.length > 0) {
                            ticketsList.innerHTML = '';

                            data.tickets.forEach(ticket => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                            <td class="px-4">
                                <strong class="text-primary">${ticket.ticket_code}</strong>
                            </td>
                            <td>${ticket.subject}</td>
                            <td><span class="status-badge ${ticket.status_badge_class}">${ticket.status}</span></td>
                            <td><span class="priority-badge ${ticket.priority_badge_class}">${ticket.priority}</span></td>
                            <td><small class="text-muted">${ticket.created_at}</small></td>
                            <td>
                                <a href="/ticket-portal/view/${ticket.tracking_token}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> {{ __('View') }}
                                </a>
                            </td>
                        `;
                                ticketsList.appendChild(row);
                            });

                            resultsContainer.classList.remove('d-none');
                        } else {
                            noResultsContainer.classList.remove('d-none');
                        }
                    } else {
                        noResultsContainer.classList.remove('d-none');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert(document.getElementById('alertContainer'), 'danger',
                        '{{ __('Something went wrong. Please try again.') }}');
                } finally {
                    hideLoading(submitBtn);
                }
            });
        });
    </script>
@endsection
