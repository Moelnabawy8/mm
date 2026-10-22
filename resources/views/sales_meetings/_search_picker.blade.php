@php
    /**
     * Server-searched picker (lead or deal) for the sales meeting screens.
     *
     * Replaces <select class="select2"> lists that rendered EVERY lead / deal of
     * the company: Choices.js (public/js/custom.js select2()) builds a DOM node per
     * option on page load, which froze the browser on big tenants.
     *
     * The value lives in a hidden input with id $pickerId, so existing JS that
     * reads $('#filter_lead').val() or listens to change on '#lead_select' works.
     *
     * @var string   $pickerId
     * @var string   $fieldName
     * @var string   $url           JSON endpoint returning [{id, text}]
     * @var int|null $selectedId
     * @var string   $selectedName
     * @var string   $placeholder
     * @var bool     $required
     */
    $selectedId = $selectedId ?? null;
    $selectedName = $selectedName ?? '';
    $placeholder = $placeholder ?? __('Search');
    $required = $required ?? false;
@endphp

<div class="sm-picker position-relative" data-url="{{ $url }}" @if ($required) data-required="1" @endif>
    <div class="input-group">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="text" class="form-control sm-picker-search" id="{{ $pickerId }}_search"
            value="{{ $selectedName }}" autocomplete="off" placeholder="{{ $placeholder }}">
        <button class="btn btn-outline-secondary sm-picker-clear" type="button" tabindex="-1"
            title="{{ __('Clear') }}"><i class="ti ti-x"></i></button>
    </div>
    <input type="hidden" name="{{ $fieldName }}" id="{{ $pickerId }}" value="{{ $selectedId }}">
    <div class="list-group sm-picker-results shadow-sm"
        style="display:none;position:absolute;z-index:1061;width:100%;max-height:240px;overflow-y:auto;"></div>
</div>

<script>
    window.SM_PICKER_TEXT = {
        none: "{{ __('No results found') }}",
        required: "{{ __('Please pick a value from the list.') }}"
    };

    // Delegated handlers, bound once: the partial is rendered again inside every
    // AJAX modal. On the index page it sits in @yield('content'), which the admin
    // layout prints before the footer loads jQuery, so wait for DOMContentLoaded.
    (function initSmPicker() {
        if (typeof window.jQuery === 'undefined') {
            document.addEventListener('DOMContentLoaded', initSmPicker);
            return;
        }
        if (window.__smPickerBound) {
            return;
        }
        window.__smPickerBound = true;

        (function($) {
            var timer = null;
            var xhr = null;

            function results($wrap) {
                return $wrap.find('.sm-picker-results');
            }

            function hidden($wrap) {
                return $wrap.find('input[type="hidden"]');
            }

            $(document).on('input', '.sm-picker-search', function() {
                var $wrap = $(this).closest('.sm-picker');
                var term = $.trim($(this).val());

                // Typing invalidates the previous pick, so a half-typed name is never submitted.
                if (hidden($wrap).val()) {
                    hidden($wrap).val('').trigger('change');
                }
                $(this).removeClass('is-invalid');
                clearTimeout(timer);

                if (term.length < 2) {
                    results($wrap).hide().empty();
                    return;
                }

                timer = setTimeout(function() {
                    if (xhr) {
                        xhr.abort();
                    }
                    xhr = $.get($wrap.data('url'), {
                        q: term
                    }, function(res) {
                        var $list = results($wrap).empty();
                        if (!res || !res.length) {
                            $list.append($('<span class="list-group-item text-muted small"></span>')
                                .text(window.SM_PICKER_TEXT.none));
                        } else {
                            $.each(res, function(i, item) {
                                var $opt = $('<a href="#" class="list-group-item list-group-item-action sm-picker-option"></a>')
                                    .attr('data-id', item.id);

                                // Name and phone go on separate lines, each with its
                                // own direction. On one line an Arabic name and a
                                // Latin-digit phone swap places and read as if the
                                // number came first. Endpoints that send no name
                                // (the deal search) still work through item.text.
                                var label = item.name != null ? item.name : item.text;
                                $opt.attr('data-label', label);
                                $opt.append($('<span class="d-block" dir="auto"></span>').text(label));
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

            $(document).on('click', '.sm-picker-option', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.sm-picker');
                // data-label, not .text(): the option now holds two elements, and
                // their concatenated text would glue the phone onto the name.
                var label = $(this).attr('data-label');
                $wrap.find('.sm-picker-search').val(label != null ? label : $(this).text()).removeClass('is-invalid');
                hidden($wrap).val($(this).data('id')).trigger('change');
                results($wrap).hide().empty();
            });

            $(document).on('click', '.sm-picker-clear', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.sm-picker');
                $wrap.find('.sm-picker-search').val('').removeClass('is-invalid');
                hidden($wrap).val('').trigger('change');
                results($wrap).hide().empty();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sm-picker').length) {
                    $('.sm-picker-results').hide();
                }
            });

            $(document).on('submit', 'form', function(e) {
                var bad = false;
                $(this).find('.sm-picker[data-required="1"]').each(function() {
                    if (!hidden($(this)).val()) {
                        bad = true;
                        $(this).find('.sm-picker-search').addClass('is-invalid').focus();
                    }
                });
                if (bad) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof show_toastr === 'function') {
                        show_toastr('Error', window.SM_PICKER_TEXT.required, 'error');
                    }
                    return false;
                }
            });
        })(window.jQuery);
    })();
</script>
