@php
    /**
     * Server-searched Lead picker.
     *
     * Replaces the old <select class="select2"> that rendered EVERY lead of the
     * company: that markup grew with the leads table and Choices.js then built a
     * DOM node per option, which locked the browser up on page load.
     *
     * Exposes the same value under the same id, so existing JS that reads
     * $('#filter_lead').val() keeps working.
     *
     * @var string      $pickerId     id of the hidden field (and "<id>_search" for the text box)
     * @var string      $fieldName    submitted field name
     * @var int|null    $selectedId
     * @var string      $selectedName
     * @var bool        $required     block form submit when nothing is picked
     */
    $pickerId = $pickerId ?? 'lead_picker';
    $fieldName = $fieldName ?? 'lead_id';
    $selectedId = $selectedId ?? null;
    $selectedName = $selectedName ?? '';
    $required = $required ?? false;
    // Defaults to the deals endpoint, so every caller written before this stays
    // as it was. Other modules pass their own, scoped to their own permissions.
    $searchUrl = $searchUrl ?? route('deals.leads.search');
@endphp

<div class="deal-lead-picker position-relative" @if ($required) data-lead-required="1" @endif>
    <div class="input-group">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="text" class="form-control deal-lead-search" id="{{ $pickerId }}_search"
            value="{{ $selectedName }}" autocomplete="off" placeholder="{{ __('Search lead by name, phone or email') }}">
        <button class="btn btn-outline-secondary deal-lead-clear" type="button" tabindex="-1"
            title="{{ __('Clear') }}"><i class="ti ti-x"></i></button>
    </div>
    <input type="hidden" name="{{ $fieldName }}" id="{{ $pickerId }}" value="{{ $selectedId }}">
    <div class="list-group deal-lead-results shadow-sm"
        style="display:none;position:absolute;z-index:1061;width:100%;max-height:240px;overflow-y:auto;"></div>
</div>

<script>
    window.DEAL_LEAD_SEARCH_URL = "{{ $searchUrl }}";
    window.DEAL_LEAD_TEXT = {
        none: "{{ __('No leads found') }}",
        min: "{{ __('Type at least 2 characters') }}",
        required: "{{ __('Please pick a lead from the list.') }}"
    };

    // Handlers are delegated on document and registered once: this partial is
    // rendered again inside every AJAX modal (create / edit deal).
    //
    // On the index page this markup sits in @yield('content'), which the admin
    // layout prints BEFORE the footer loads jQuery, so initialisation waits for
    // DOMContentLoaded. Inside an AJAX modal jQuery is already there and the
    // first branch runs immediately.
    (function initDealLeadPicker() {
        if (typeof window.jQuery === 'undefined') {
            document.addEventListener('DOMContentLoaded', initDealLeadPicker);
            return;
        }
        if (window.__dealLeadPickerBound) {
            return;
        }
        window.__dealLeadPickerBound = true;

        (function($) {
            var timer = null;
            var xhr = null;

            function box($wrap) {
                return $wrap.find('.deal-lead-results');
            }

            function hidden($wrap) {
                return $wrap.find('input[type="hidden"]');
            }

            $(document).on('input', '.deal-lead-search', function() {
                var $wrap = $(this).closest('.deal-lead-picker');
                var term = $.trim($(this).val());

                // Typing invalidates the previous pick, so a half-typed name can
                // never be submitted as if it were the selected lead.
                hidden($wrap).val('');
                $(this).removeClass('is-invalid');
                clearTimeout(timer);

                if (term.length < 2) {
                    box($wrap).hide().empty();
                    return;
                }

                timer = setTimeout(function() {
                    if (xhr) {
                        xhr.abort();
                    }
                    xhr = $.get(window.DEAL_LEAD_SEARCH_URL, {
                        q: term
                    }, function(res) {
                        var $list = box($wrap).empty();
                        if (!res || !res.length) {
                            $list.append($('<span class="list-group-item text-muted small"></span>')
                                .text(window.DEAL_LEAD_TEXT.none));
                        } else {
                            $.each(res, function(i, item) {
                                var $opt = $(
                                    '<a href="#" class="list-group-item list-group-item-action deal-lead-option"></a>'
                                ).attr('data-id', item.id);

                                // Name and phone go on separate lines, each with its
                                // own direction. On one line an Arabic name and a
                                // Latin-digit phone swap places and read as if the
                                // number came first.
                                var name = item.name != null ? item.name : item.text;
                                $opt.attr('data-label', name);
                                $opt.append($('<span class="d-block" dir="auto"></span>').text(name));
                                if (item.phone) {
                                    $opt.append($(
                                            '<span class="d-block small text-muted" dir="ltr" style="text-align:start"></span>'
                                        )
                                        .text(item.phone));
                                }
                                $list.append($opt);
                            });
                        }
                        $list.show();
                    });
                }, 300);
            });

            $(document).on('click', '.deal-lead-option', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.deal-lead-picker');
                hidden($wrap).val($(this).data('id')).trigger('change');
                // data-label, not .text(): the option now holds two elements, and
                // their concatenated text would glue the phone onto the name.
                var label = $(this).attr('data-label');
                $wrap.find('.deal-lead-search').val(label != null ? label : $(this).text()).removeClass('is-invalid');
                box($wrap).hide().empty();
            });

            $(document).on('click', '.deal-lead-clear', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.deal-lead-picker');
                hidden($wrap).val('').trigger('change');
                $wrap.find('.deal-lead-search').val('').removeClass('is-invalid');
                box($wrap).hide().empty();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.deal-lead-picker').length) {
                    $('.deal-lead-results').hide();
                }
            });

            // Only fires for forms that actually contain a required picker.
            $(document).on('submit', 'form', function(e) {
                var bad = false;
                $(this).find('.deal-lead-picker[data-lead-required="1"]').each(function() {
                    if (!hidden($(this)).val()) {
                        bad = true;
                        $(this).find('.deal-lead-search').addClass('is-invalid').focus();
                    }
                });
                if (bad) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof show_toastr === 'function') {
                        show_toastr('Error', window.DEAL_LEAD_TEXT.required, 'error');
                    }
                    return false;
                }
            });
        })(window.jQuery);
    })();
</script>
