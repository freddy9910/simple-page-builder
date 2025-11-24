jQuery(document).ready(function($) {
    'use strict';
    
    // API Key Generation
    $('#spb-generate-key-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).html('<span class="spb-loading"></span> Generating...');
        
        var formData = {
            action: 'spb_generate_api_key',
            nonce: spb_ajax.nonce,
            key_name: $('#key_name').val(),
            expiration_days: $('#expiration_days').val()
        };
        
        $.post(spb_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    // Show the API key in modal
                    $('#generated-api-key').val(response.data.api_key);
                    $('#spb-api-key-modal').show();
                    
                    // Reset form
                    $form[0].reset();
                    
                    // Refresh API keys table
                    refreshApiKeysTable();
                } else {
                    showNotice('error', response.data);
                }
            })
            .fail(function() {
                showNotice('error', 'An error occurred while generating the API key.');
            })
            .always(function() {
                $button.prop('disabled', false).text(originalText);
            });
    });
    
    // Modal functionality
    $('.spb-modal-close').on('click', function() {
        $(this).closest('.spb-modal').hide();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('spb-modal')) {
            $('.spb-modal').hide();
        }
    });
    
    // Copy API key to clipboard
    $('#copy-api-key').on('click', function() {
        var $input = $('#generated-api-key');
        $input.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                $(this).text('Copied!').prop('disabled', true);
                setTimeout(function() {
                    $('#copy-api-key').text('Copy').prop('disabled', false);
                }, 2000);
            } else {
                alert(spb_ajax.strings.copy_failed);
            }
        } catch (err) {
            alert(spb_ajax.strings.copy_failed);
        }
    });
    
    // Revoke API key
    $(document).on('click', '.revoke-api-key', function(e) {
        e.preventDefault();
        
        if (!confirm(spb_ajax.strings.confirm_revoke)) {
            return;
        }
        
        var $button = $(this);
        var keyId = $button.data('key-id');
        var $row = $button.closest('tr');
        
        $button.prop('disabled', true).text('Revoking...');
        
        $.post(spb_ajax.ajax_url, {
            action: 'spb_revoke_api_key',
            nonce: spb_ajax.nonce,
            key_id: keyId
        })
        .done(function(response) {
            if (response.success) {
                // Update the row to show revoked status
                $row.find('.spb-status').removeClass('spb-status-active').addClass('spb-status-revoked').text('Revoked');
                $button.remove();
                showNotice('success', response.data);
            } else {
                showNotice('error', response.data);
                $button.prop('disabled', false).text('Revoke');
            }
        })
        .fail(function() {
            showNotice('error', 'An error occurred while revoking the API key.');
            $button.prop('disabled', false).text('Revoke');
        });
    });
    
    // Refresh API keys table
    $('#refresh-api-keys').on('click', function() {
        refreshApiKeysTable();
    });
    
    function refreshApiKeysTable() {
        var $button = $('#refresh-api-keys');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Refreshing...');
        
        $.post(spb_ajax.ajax_url, {
            action: 'spb_get_api_keys',
            nonce: spb_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                updateApiKeysTable(response.data);
            } else {
                showNotice('error', response.data);
            }
        })
        .fail(function() {
            showNotice('error', 'Failed to refresh API keys.');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    }
    
    function updateApiKeysTable(apiKeys) {
        var $tbody = $('#api-keys-table-body');
        $tbody.empty();
        
        if (apiKeys.length === 0) {
            $tbody.append('<tr><td colspan="7">No API keys found.</td></tr>');
            return;
        }
        
        apiKeys.forEach(function(key) {
            var statusClass = 'spb-status-' + key.status;
            var statusText = key.status.charAt(0).toUpperCase() + key.status.slice(1);
            var lastUsed = key.last_used ? formatDate(key.last_used) : 'Never';
            var createdDate = formatDate(key.created_date);
            
            var actionsHtml = '';
            if (key.status === 'active') {
                actionsHtml = '<button type="button" class="button button-small revoke-api-key" data-key-id="' + key.id + '">Revoke</button>';
            }
            
            var rowHtml = '<tr data-key-id="' + key.id + '">' +
                '<td><strong>' + escapeHtml(key.key_name) + '</strong></td>' +
                '<td><code>' + escapeHtml(key.key_preview) + '</code></td>' +
                '<td><span class="spb-status ' + statusClass + '">' + statusText + '</span></td>' +
                '<td>' + createdDate + '</td>' +
                '<td>' + lastUsed + '</td>' +
                '<td>' + parseInt(key.request_count).toLocaleString() + '</td>' +
                '<td>' + actionsHtml + '</td>' +
                '</tr>';
            
            $tbody.append(rowHtml);
        });
    }
    
    // Settings form
    $('#spb-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Saving...');
        
        var formData = {
            action: 'spb_save_settings',
            nonce: spb_ajax.nonce,
            webhook_url: $('#webhook_url').val(),
            webhook_secret: $('#webhook_secret').val(),
            rate_limit_per_hour: $('#rate_limit_per_hour').val(),
            api_enabled: $('#api_enabled').is(':checked') ? 1 : 0
        };
        
        $.post(spb_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', response.data);
                } else {
                    showNotice('error', response.data);
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to save settings.');
            })
            .always(function() {
                $button.prop('disabled', false).text(originalText);
            });
    });
    
    // Generate webhook secret
    $('#generate-webhook-secret').on('click', function() {
        var newSecret = generateRandomString(32);
        $('#webhook_secret').val(newSecret);
    });
    
    // Test webhook
    $('#test-webhook').on('click', function() {
        var $button = $(this);
        var $result = $('#webhook-test-result');
        var webhookUrl = $('#webhook_url').val();
        
        if (!webhookUrl) {
            $result.html('<div class="spb-notice spb-notice-error">Please enter a webhook URL first.</div>');
            return;
        }
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('<div class="spb-notice">Testing webhook...</div>');
        
        $.post(spb_ajax.ajax_url, {
            action: 'spb_test_webhook',
            nonce: spb_ajax.nonce,
            webhook_url: webhookUrl
        })
        .done(function(response) {
            var noticeClass = response.success ? 'spb-notice-success' : 'spb-notice-error';
            $result.html('<div class="spb-notice ' + noticeClass + '">' + response.data + '</div>');
        })
        .fail(function() {
            $result.html('<div class="spb-notice spb-notice-error">Failed to test webhook.</div>');
        })
        .always(function() {
            $button.prop('disabled', false).text('Test Webhook');
        });
    });
    
    // Export logs
    $('#export-logs').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Exporting...');
        
        $.post(spb_ajax.ajax_url, {
            action: 'spb_export_logs',
            nonce: spb_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                // Create and trigger download
                var csvContent = atob(response.data.content);
                var blob = new Blob([csvContent], { type: 'text/csv' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showNotice('success', 'Logs exported successfully.');
            } else {
                showNotice('error', response.data);
            }
        })
        .fail(function() {
            showNotice('error', 'Failed to export logs.');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Utility functions
    function showNotice(type, message) {
        var noticeClass = 'spb-notice-' + type;
        var $notice = $('<div class="spb-notice ' + noticeClass + '">' + message + '</div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
        
        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.getFullYear() + '-' +
               String(date.getMonth() + 1).padStart(2, '0') + '-' +
               String(date.getDate()).padStart(2, '0') + ' ' +
               String(date.getHours()).padStart(2, '0') + ':' +
               String(date.getMinutes()).padStart(2, '0');
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function generateRandomString(length) {
        var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        var result = '';
        for (var i = length; i > 0; --i) {
            result += chars[Math.floor(Math.random() * chars.length)];
        }
        return result;
    }
    
    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('[data-tooltip]').tooltip();
    }
});