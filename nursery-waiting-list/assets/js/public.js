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
            
            // New search button
            $(document).on('click', '#nwl-new-search', this.handleNewSearch.bind(this));
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
            } else {
                $label.text(nwlPublic.strings.phoneLabel || 'Phone Number');
                $input.attr('placeholder', nwlPublic.strings.phonePlaceholder || 'Enter your phone number');
                $input.attr('type', 'tel');
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
            $('#nwl-lookup-results').hide().empty();
            $('#nwl-lookup-form').show();
            $('#nwl-lookup-value').val('').focus();
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
            $('#nwl-lookup-form').hide();
            $('#nwl-lookup-results').html(html).show();
        },

        showError: function(message) {
            var html = '<div class="nwl-error-message">' +
                '<div class="nwl-error-icon">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                        '<circle cx="12" cy="12" r="10"></circle>' +
                        '<line x1="12" y1="8" x2="12" y2="12"></line>' +
                        '<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
                    '</svg>' +
                '</div>' +
                '<h3>' + (nwlPublic.strings.notFoundTitle || 'No Results Found') + '</h3>' +
                '<p>' + message + '</p>' +
                '<button type="button" class="nwl-new-search-btn" id="nwl-new-search">' +
                    (nwlPublic.strings.tryAgain || 'Try Again') +
                '</button>' +
            '</div>';
            
            $('#nwl-lookup-form').hide();
            $('#nwl-lookup-results').html(html).show();
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
    };

    // Make available globally
    window.NWL_Public = NWL_Public;

})(jQuery);
