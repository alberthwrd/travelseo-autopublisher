/**
 * TravelSEO Autopublisher Admin JavaScript
 *
 * @package TravelSEO_Autopublisher
 */

(function($) {
    'use strict';

    // Update nonce (will be set from PHP)
    var updateNonce = (typeof tsaAdmin !== 'undefined') ? tsaAdmin.updateNonce : '';

    /**
     * Initialize admin functionality
     */
    function init() {
        initTabs();
        initBulkActions();
        initAutoRefresh();
        initConfirmDialogs();
        initUpdateChecker();
    }

    /**
     * Tab navigation
     */
    function initTabs() {
        $('.tsa-tab-link').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Update active states
            $('.tsa-tab-link').removeClass('active');
            $(this).addClass('active');
            
            $('.tsa-tab-content').removeClass('active');
            $(target).addClass('active');
            
            // Update URL hash
            history.replaceState(null, null, target);
        });
        
        // Check for hash on page load
        if (window.location.hash) {
            var hash = window.location.hash;
            if ($('.tsa-tab-link[href="' + hash + '"]').length) {
                $('.tsa-tab-link[href="' + hash + '"]').trigger('click');
            }
        }
    }

    /**
     * Bulk actions
     */
    function initBulkActions() {
        // Select all checkbox
        $('#cb-select-all').on('change', function() {
            $('input[name="job_ids[]"]').prop('checked', $(this).prop('checked'));
        });
        
        // Bulk action buttons
        $('.tsa-bulk-action').on('click', function(e) {
            var action = $(this).data('action');
            var selectedJobs = $('input[name="job_ids[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedJobs.length === 0) {
                alert('Please select at least one job.');
                e.preventDefault();
                return;
            }
            
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete ' + selectedJobs.length + ' job(s)?')) {
                    e.preventDefault();
                    return;
                }
            }
        });
    }

    /**
     * Auto-refresh for processing jobs
     */
    function initAutoRefresh() {
        // Only on jobs list page with processing jobs
        if ($('.tsa-status-researching, .tsa-status-drafting, .tsa-status-qa, .tsa-status-image_planning').length > 0) {
            setTimeout(function() {
                location.reload();
            }, 30000); // Refresh every 30 seconds
        }
        
        // Job detail page auto-refresh
        if ($('.tsa-job-detail').length > 0) {
            var status = $('.tsa-status-badge').text().trim().toLowerCase();
            if (['queued', 'researching', 'drafting', 'qa', 'image planning'].indexOf(status) !== -1) {
                setTimeout(function() {
                    location.reload();
                }, 10000); // Refresh every 10 seconds for detail page
            }
        }
    }

    /**
     * Confirmation dialogs
     */
    function initConfirmDialogs() {
        $('a.submitdelete').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
        
        $('a[data-confirm]').on('click', function(e) {
            var message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    }

    /**
     * Initialize Update Checker
     */
    function initUpdateChecker() {
        // Get nonce from hidden field or generate
        updateNonce = $('#tsa_update_nonce').val() || '';
        
        // Check for updates on settings page load
        if ($('#github-update').length > 0) {
            // Auto-check on page load (with slight delay)
            setTimeout(function() {
                checkForUpdates(true);
            }, 500);
        }
        
        // Check for Updates button
        $('#tsa-check-update').on('click', function(e) {
            e.preventDefault();
            checkForUpdates(false);
        });
        
        // Update Now button
        $('#tsa-do-update').on('click', function(e) {
            e.preventDefault();
            doPluginUpdate();
        });
        
        // View Changelog toggle
        $('#tsa-view-changelog').on('click', function(e) {
            e.preventDefault();
            $('#tsa-changelog-box').slideToggle();
        });
    }

    /**
     * Check for updates
     */
    function checkForUpdates(silent) {
        var $btn = $('#tsa-check-update');
        var $status = $('#tsa-check-status');
        
        if (!silent) {
            $btn.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span> Checking...');
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tsa_force_update_check',
                nonce: updateNonce || $('#_wpnonce').val()
            },
            success: function(response) {
                if (response.success) {
                    displayUpdateInfo(response.data.info);
                    if (!silent) {
                        $status.html('<span style="color:green;">✓ Check complete</span>');
                        setTimeout(function() {
                            $status.html('');
                        }, 3000);
                    }
                } else {
                    if (!silent) {
                        $status.html('<span style="color:red;">✗ ' + (response.data.message || 'Check failed') + '</span>');
                    }
                }
            },
            error: function() {
                if (!silent) {
                    $status.html('<span style="color:red;">✗ Connection error</span>');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Display update information
     */
    function displayUpdateInfo(info) {
        var $notification = $('#tsa-update-notification');
        var $uptodate = $('#tsa-uptodate-notification');
        var $badge = $('#tsa-version-badge');
        
        // Hide both first
        $notification.hide();
        $uptodate.hide();
        
        if (!info.github_connected) {
            $badge.html('<span class="tsa-badge-warning">Not Connected</span>');
            return;
        }
        
        if (info.update_available) {
            // Show update notification
            $('#tsa-latest-ver').text('v' + info.latest_version);
            $('#tsa-release-date').text(info.release_date_formatted || 'Unknown');
            $('#tsa-changelog-content').html(info.release_notes_html || '<p>No changelog available.</p>');
            $('#tsa-release-link').attr('href', info.release_url || '#');
            
            $notification.slideDown();
            $badge.html('<span class="tsa-badge-update">Update Available: v' + info.latest_version + '</span>');
        } else {
            // Show up to date message
            $uptodate.slideDown();
            $badge.html('<span class="tsa-badge-ok">Up to Date</span>');
        }
    }

    /**
     * Perform plugin update
     */
    function doPluginUpdate() {
        var $btn = $('#tsa-do-update');
        var $progress = $('#tsa-update-progress');
        var $notification = $('#tsa-update-notification');
        
        // Confirm
        if (!confirm('Are you sure you want to update the plugin now? The page will reload after the update.')) {
            return;
        }
        
        // Show progress
        $btn.prop('disabled', true);
        $notification.hide();
        $progress.show();
        
        $('#tsa-progress-title').text('Downloading update...');
        $('#tsa-progress-message').text('Please wait while the plugin is being updated. Do not close this page.');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tsa_do_plugin_update',
                nonce: updateNonce || $('#_wpnonce').val()
            },
            timeout: 120000, // 2 minutes timeout
            success: function(response) {
                if (response.success) {
                    $('#tsa-progress-title').text('Update Complete!');
                    $('#tsa-progress-message').html(
                        '<span style="color:green;">✓ ' + response.data.message + '</span><br>' +
                        'Reloading page in 3 seconds...'
                    );
                    
                    // Reload page
                    setTimeout(function() {
                        window.location.reload();
                    }, 3000);
                } else {
                    $('#tsa-progress-title').text('Update Failed');
                    $('#tsa-progress-message').html(
                        '<span style="color:red;">✗ ' + (response.data.message || 'Unknown error') + '</span><br>' +
                        '<button type="button" class="button" onclick="location.reload()">Reload Page</button>'
                    );
                    $progress.find('.spinner').removeClass('is-active');
                }
            },
            error: function(xhr, status, error) {
                $('#tsa-progress-title').text('Update Failed');
                $('#tsa-progress-message').html(
                    '<span style="color:red;">✗ Connection error: ' + error + '</span><br>' +
                    'Please try again or update manually.<br>' +
                    '<button type="button" class="button" onclick="location.reload()">Reload Page</button>'
                );
                $progress.find('.spinner').removeClass('is-active');
            }
        });
    }

    /**
     * AJAX job processing status check
     */
    function checkJobStatus(jobId, callback) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tsa_check_job_status',
                job_id: jobId,
                nonce: tsaAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data);
                }
            }
        });
    }

    /**
     * Preview modal
     */
    function openPreviewModal(jobId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tsa_get_preview',
                job_id: jobId,
                nonce: tsaAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showModal(response.data.html);
                }
            }
        });
    }

    /**
     * Show modal
     */
    function showModal(content) {
        var modal = $('<div class="tsa-modal-overlay"><div class="tsa-modal">' +
            '<button class="tsa-modal-close">&times;</button>' +
            '<div class="tsa-modal-content">' + content + '</div>' +
            '</div></div>');
        
        $('body').append(modal);
        
        modal.find('.tsa-modal-close').on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if ($(e.target).hasClass('tsa-modal-overlay')) {
                modal.remove();
            }
        });
    }

    /**
     * CSV Import handler
     */
    function handleCSVImport(file) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var content = e.target.result;
            var lines = content.split('\n');
            var titles = [];
            
            lines.forEach(function(line) {
                line = line.trim();
                if (line && line.length > 0) {
                    // Handle CSV with quotes
                    line = line.replace(/^"(.*)"$/, '$1');
                    titles.push(line);
                }
            });
            
            var currentTitles = $('#titles_input').val().trim();
            if (currentTitles) {
                currentTitles += '\n';
            }
            $('#titles_input').val(currentTitles + titles.join('\n'));
            
            alert(titles.length + ' titles imported successfully!');
        };
        
        reader.readAsText(file);
    }

    /**
     * Real-time word count
     */
    function updateWordCount(textarea) {
        var text = $(textarea).val();
        var lines = text.split('\n').filter(function(line) {
            return line.trim().length > 0;
        });
        
        var countDisplay = $(textarea).siblings('.tsa-line-count');
        if (countDisplay.length === 0) {
            countDisplay = $('<span class="tsa-line-count"></span>');
            $(textarea).after(countDisplay);
        }
        
        countDisplay.text(lines.length + ' titles');
    }

    // Initialize on document ready
    $(document).ready(function() {
        init();
        
        // Title count for textarea
        $('#titles_input').on('input', function() {
            updateWordCount(this);
        }).trigger('input');
        
        // CSV import button
        $('#import_csv').on('click', function() {
            var fileInput = $('#csv_file')[0];
            if (fileInput.files.length === 0) {
                alert('Please select a CSV file first.');
                return;
            }
            handleCSVImport(fileInput.files[0]);
        });
        
        // Category mode toggle
        $('#category_mode').on('change', function() {
            if ($(this).val() === 'select') {
                $('#category_select_row').show();
            } else {
                $('#category_select_row').hide();
            }
        });
    });

})(jQuery);
