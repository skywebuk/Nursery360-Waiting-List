/**
 * Nursery Waiting List - Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        NWL_Admin.init();
    });

    var NWL_Admin = {

        init: function() {
            this.bindEvents();
            this.initDatePickers();
        },

        bindEvents: function() {
            // Select all checkbox
            $('#nwl-select-all').on('change', this.handleSelectAll);
            
            // Bulk action
            $('#nwl-apply-bulk').on('click', this.handleBulkAction);
            
            // Bulk action change
            $('#nwl-bulk-action').on('change', this.handleBulkActionChange);
            
            // Status change
            $('.nwl-status-select').on('change', this.handleStatusChange);
            
            // Add note
            $('.nwl-add-note-btn').on('click', this.handleAddNote);
            
            // Delete entry
            $('.nwl-delete-entry').on('click', this.handleDeleteEntry);
            
            // Hard delete
            $('.nwl-hard-delete').on('click', this.handleHardDelete);
            
            // Cancel deletion
            $('.nwl-cancel-deletion').on('click', this.handleCancelDeletion);
            
            // Delete template
            $('.nwl-delete-template').on('click', this.handleDeleteTemplate);
            
            // Entry form submit
            $('#nwl-entry-form').on('submit', this.handleEntryFormSubmit);
        },

        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.nwl-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd'
                });
            }
        },

        handleSelectAll: function() {
            var checked = $(this).prop('checked');
            $('input[name="entry_ids[]"]').prop('checked', checked);
        },

        handleBulkActionChange: function() {
            var action = $(this).val();
            if (action === 'change_status') {
                $('#nwl-bulk-status').show();
            } else {
                $('#nwl-bulk-status').hide();
            }
        },

        handleBulkAction: function() {
            var action = $('#nwl-bulk-action').val();
            var entryIds = [];
            
            $('input[name="entry_ids[]"]:checked').each(function() {
                entryIds.push($(this).val());
            });

            if (!action) {
                alert(nwlAdmin.strings.selectEntries);
                return;
            }

            if (entryIds.length === 0) {
                alert(nwlAdmin.strings.selectEntries);
                return;
            }

            if (action === 'delete' && !confirm(nwlAdmin.strings.confirmBulkDelete)) {
                return;
            }

            var data = {
                action: 'nwl_bulk_action',
                nonce: nwlAdmin.nonce,
                bulk_action: action,
                entry_ids: entryIds,
                bulk_status: $('#nwl-bulk-status').val()
            };

            $.post(nwlAdmin.ajaxUrl, data, function(response) {
                if (response.success) {
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                }
            });
        },

        handleStatusChange: function() {
            var $select = $(this);
            var entryId = $select.data('entry-id');
            var status = $select.val();

            $.post(nwlAdmin.ajaxUrl, {
                action: 'nwl_update_status',
                nonce: nwlAdmin.nonce,
                entry_id: entryId,
                status: status
            }, function(response) {
                if (response.success) {
                    // Optionally show notification
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                    location.reload();
                }
            });
        },

        handleAddNote: function() {
            var $btn = $(this);
            var entryId = $btn.data('entry-id');
            var noteType = $('#note-type').val();
            var content = $('#note-content').val();

            if (!content.trim()) {
                alert('Please enter note content.');
                return;
            }

            $btn.prop('disabled', true);

            $.post(nwlAdmin.ajaxUrl, {
                action: 'nwl_add_note',
                nonce: nwlAdmin.nonce,
                entry_id: entryId,
                note_type: noteType,
                content: content
            }, function(response) {
                $btn.prop('disabled', false);
                
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                }
            });
        },

        handleDeleteEntry: function() {
            if (!confirm(nwlAdmin.strings.confirmDelete)) {
                return;
            }

            var entryId = $(this).data('entry-id');

            $.post(nwlAdmin.ajaxUrl, {
                action: 'nwl_delete_entry',
                nonce: nwlAdmin.nonce,
                entry_id: entryId,
                hard_delete: false
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                }
            });
        },

        handleHardDelete: function() {
            if (!confirm(nwlAdmin.strings.confirmDelete + ' This will permanently remove all data.')) {
                return;
            }

            var entryId = $(this).data('entry-id');

            $.post(nwlAdmin.ajaxUrl, {
                action: 'nwl_delete_entry',
                nonce: nwlAdmin.nonce,
                entry_id: entryId,
                hard_delete: 'true'
            }, function(response) {
                if (response.success) {
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                }
            });
        },

        handleCancelDeletion: function() {
            var entryId = $(this).data('entry-id');

            $.post(nwlAdmin.ajaxUrl, {
                action: 'nwl_cancel_deletion',
                nonce: nwlAdmin.nonce,
                entry_id: entryId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                }
            });
        },

        handleDeleteTemplate: function() {
            if (!confirm('Are you sure you want to delete this template?')) {
                return;
            }

            var templateId = $(this).data('template-id');

            $.post(nwlAdmin.ajaxUrl, {
                action: 'nwl_delete_template',
                nonce: nwlAdmin.nonce,
                template_id: templateId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                }
            });
        },

        handleEntryFormSubmit: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).text(nwlAdmin.strings.saving);

            $.post(nwlAdmin.ajaxUrl, $form.serialize() + '&action=nwl_save_entry', function(response) {
                if (response.success) {
                    $submitBtn.text(nwlAdmin.strings.saved);
                    
                    setTimeout(function() {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            $submitBtn.prop('disabled', false).text(originalText);
                        }
                    }, 500);
                } else {
                    alert(response.data.message || nwlAdmin.strings.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            }).fail(function() {
                alert(nwlAdmin.strings.error);
                $submitBtn.prop('disabled', false).text(originalText);
            });
        }
    };

    // Make available globally
    window.NWL_Admin = NWL_Admin;

})(jQuery);
