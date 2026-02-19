/**
 * Nursery Waiting List - Public JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        NWL_Public.init();
    });

    var NWL_Public = {

        currentType: 'email',

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Type toggle buttons
            $('.nwl-type-btn').on('click', this.handleTypeToggle.bind(this));

            // Form submission
            $('#nwl-lookup-form').on('submit', this.handleFormSubmit.bind(this));

            // New search button (delegated)
            $(document).on('click', '#nwl-new-search', this.handleNewSearch.bind(this));

            // Copy reference number (delegated)
            $(document).on('click', '.nwl-wl-number', this.handleCopyReference.bind(this));
        },

        handleTypeToggle: function(e) {
            var $btn = $(e.currentTarget);
            var type = $btn.data('type');

            this.currentType = type;

            // Update active state
            $('.nwl-type-btn').removeClass('active');
            $btn.addClass('active');

            // Update hidden input
            $('#nwl-lookup-type').val(type);

            // Update label and placeholder
            var $input = $('#nwl-lookup-value');
            var $label = $('#nwl-value-label');

            if (type === 'email') {
                $label.text(nwlPublic.strings.emailLabel || 'Email Address');
                $input.attr('placeholder', nwlPublic.strings.emailPlaceholder || 'Enter your email address');
                $input.attr('type', 'email');
                $input.attr('autocomplete', 'email');
            } else if (type === 'reference') {
                $label.text(nwlPublic.strings.referenceLabel || 'Reference Number');
                $input.attr('placeholder', nwlPublic.strings.referencePlaceholder || 'e.g. WL2024001');
                $input.attr('type', 'text');
                $input.attr('autocomplete', 'off');
            } else {
                $label.text(nwlPublic.strings.phoneLabel || 'Phone Number');
                $input.attr('placeholder', nwlPublic.strings.phonePlaceholder || 'Enter your phone number');
                $input.attr('type', 'tel');
                $input.attr('autocomplete', 'tel');
            }

            $input.val('').focus();
        },

        handleFormSubmit: function(e) {
            e.preventDefault();

            var lookupValue = $('#nwl-lookup-value').val().trim();

            if (!lookupValue) {
                this.showError(nwlPublic.strings.invalidInput);
                return;
            }

            // Validate input
            if (this.currentType === 'email' && !this.isValidEmail(lookupValue)) {
                this.showError(nwlPublic.strings.invalidInput);
                return;
            }

            this.setLoading(true);

            var self = this;

            $.ajax({
                url: nwlPublic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nwl_parent_lookup',
                    nwl_nonce: $('input[name="nwl_nonce"]').val(),
                    lookup_type: this.currentType,
                    lookup_value: lookupValue
                },
                success: function(response) {
                    self.setLoading(false);

                    if (response.success) {
                        self.showResults(response.data.html);
                    } else {
                        self.showError(response.data.message || nwlPublic.strings.notFound);
                    }
                },
                error: function() {
                    self.setLoading(false);
                    self.showError(nwlPublic.strings.error);
                }
            });
        },

        handleNewSearch: function() {
            var $results = $('#nwl-lookup-results');
            var $form = $('#nwl-lookup-form');

            $results.fadeOut(200, function() {
                $(this).empty();
                $form.fadeIn(200, function() {
                    $('#nwl-lookup-value').val('').focus();
                });
            });
        },

        handleCopyReference: function(e) {
            var $el = $(e.currentTarget);
            var ref = $el.data('ref');

            if (!ref) return;

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(ref);
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(ref).select();
                document.execCommand('copy');
                $temp.remove();
            }

            // Show tooltip
            var $tooltip = $('<span class="nwl-copied-tooltip">' + (nwlPublic.strings.copied || 'Copied!') + '</span>');
            $el.css('position', 'relative').append($tooltip);

            $tooltip.css({
                position: 'absolute',
                top: '-28px',
                left: '50%',
                transform: 'translateX(-50%)'
            });

            setTimeout(function() {
                $tooltip.remove();
            }, 1300);
        },

        setLoading: function(loading) {
            var $btn = $('#nwl-submit-btn');
            var $btnText = $btn.find('.nwl-btn-text');
            var $btnLoading = $btn.find('.nwl-btn-loading');

            if (loading) {
                $btn.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
            } else {
                $btn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        },

        showResults: function(html) {
            var $form = $('#nwl-lookup-form');
            var $results = $('#nwl-lookup-results');

            $form.fadeOut(200, function() {
                $results.html(html).fadeIn(300);
            });
        },

        showError: function(message) {
            var html = '<div class="nwl-error-message">' +
                '<div class="nwl-error-icon">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<circle cx="12" cy="12" r="10"></circle>' +
                        '<line x1="12" y1="8" x2="12" y2="12"></line>' +
                        '<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
                    '</svg>' +
                '</div>' +
                '<h3>' + (nwlPublic.strings.notFoundTitle || 'No Results Found') + '</h3>' +
                '<p>' + message + '</p>' +
                '<button type="button" class="nwl-new-search-btn" id="nwl-new-search">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg> ' +
                    (nwlPublic.strings.tryAgain || 'Try Again') +
                '</button>' +
            '</div>';

            var $form = $('#nwl-lookup-form');
            var $results = $('#nwl-lookup-results');

            $form.fadeOut(200, function() {
                $results.html(html).fadeIn(300);
            });
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
    };

    // Make available globally
    window.NWL_Public = NWL_Public;

})(jQuery);
