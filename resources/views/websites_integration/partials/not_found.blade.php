<div class="text-center py-5">
    <i class="ti ti-mood-empty fs-1 d-block mb-2 opacity-50"></i>
    <h5 class="text-muted">{{ __('Not found') }}</h5>
    <p class="text-muted small">{{ __('The :type could not be loaded. It may have been deleted or the API is unreachable.', ['type' => $type ?? 'item']) }}</p>
</div>
