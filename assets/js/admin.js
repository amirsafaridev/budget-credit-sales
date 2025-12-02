/**
 * Admin JavaScript - User Credit Management
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Initialize popups
        initPopups();
        
        // Initialize search
        initSearch();
        
        // Initialize form handlers
        initFormHandlers();
        
        // Initialize button handlers
        initButtonHandlers();
    });
    
    /**
     * Initialize popup functionality
     */
    function initPopups() {
        // Close popup on overlay click
        $(document).on('click', '.arta-budget-popup-overlay', function(e) {
            if (e.target === this) {
                closePopup($(this).closest('.arta-budget-popup'));
            }
        });
        
        // Close popup on close button click
        $(document).on('click', '.arta-budget-popup-close', function() {
            closePopup($(this).closest('.arta-budget-popup'));
        });
        
        // Close popup on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $('.arta-budget-popup.active').each(function() {
                    closePopup($(this));
                });
            }
        });
    }
    
    /**
     * Open popup
     */
    function openPopup($popup) {
        $popup.addClass('active');
        $('body').css('overflow', 'hidden');
    }
    
    /**
     * Close popup
     */
    function closePopup($popup) {
        $popup.removeClass('active');
        $('body').css('overflow', '');
        
        // Reset forms
        $popup.find('form')[0]?.reset();
        $popup.find('.arta-budget-form-input').val('');
        $popup.find('.arta-budget-form-textarea').val('');
    }
    
    /**
     * Initialize search functionality
     */
    function initSearch() {
        $('#arta-budget-user-search').on('input', function() {
            var searchTerm = $(this).val().toLowerCase().trim();
            var $rows = $('.arta-budget-modern-table tbody tr');
            
            if (searchTerm === '') {
                $rows.show();
                return;
            }
            
            $rows.each(function() {
                var $row = $(this);
                var userId = $row.data('user-id')?.toString() || '';
                var userLogin = $row.data('user-login') || '';
                var userName = $row.data('user-name') || '';
                var userEmail = $row.data('user-email') || '';
                
                var matches = 
                    userId.includes(searchTerm) ||
                    userLogin.includes(searchTerm) ||
                    userName.includes(searchTerm) ||
                    userEmail.includes(searchTerm);
                
                if (matches) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        });
    }
    
    /**
     * Initialize button handlers
     */
    function initButtonHandlers() {
        // Increase credit button
        $(document).on('click', '.arta-budget-btn-increase', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');
            var currentCredit = parseFloat($(this).data('current-credit')) || 0;
            
            $('#popup-increase-user-id').val(userId);
            $('#popup-increase-user-name').text(userName);
            $('#popup-increase-current-credit').text(formatNumber(currentCredit));
            $('#popup-increase-amount').val('');
            $('#popup-increase-reason').val('');
            
            openPopup($('#arta-budget-popup-increase'));
        });
        
        // Decrease credit button
        $(document).on('click', '.arta-budget-btn-decrease', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');
            var currentCredit = parseFloat($(this).data('current-credit')) || 0;
            
            $('#popup-decrease-user-id').val(userId);
            $('#popup-decrease-user-name').text(userName);
            $('#popup-decrease-current-credit').text(formatNumber(currentCredit));
            $('#popup-decrease-amount').val('');
            $('#popup-decrease-reason').val('');
            
            openPopup($('#arta-budget-popup-decrease'));
        });
        
        // History button
        $(document).on('click', '.arta-budget-btn-history', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');
            
            $('#popup-history-user-name').text(userName);
            $('#arta-budget-history-content').html(
                '<div class="arta-budget-loading">' +
                '<span class="spinner is-active"></span> ' +
                'در حال بارگذاری...' +
                '</div>'
            );
            
            openPopup($('#arta-budget-popup-history'));
            
            // Load history via AJAX
            loadCreditHistory(userId);
        });
    }
    
    /**
     * Initialize form handlers
     */
    function initFormHandlers() {
        // Increase credit form
        $('#arta-budget-form-increase').on('submit', function(e) {
            e.preventDefault();
            updateCredit('increase', $(this));
        });
        
        // Decrease credit form
        $('#arta-budget-form-decrease').on('submit', function(e) {
            e.preventDefault();
            updateCredit('decrease', $(this));
        });
    }
    
    /**
     * Update credit via AJAX
     */
    function updateCredit(operation, $form) {
        var userId = $form.find('input[name="user_id"]').val();
        var amount = parseFloat($form.find('input[name="amount"]').val());
        var reason = $form.find('textarea[name="reason"]').val();
        
        // Validation
        if (!userId || userId <= 0) {
            showMessage('شناسه کاربر نامعتبر است.', 'error');
            return;
        }
        
        if (!amount || amount <= 0) {
            showMessage('مقدار باید بیشتر از صفر باشد.', 'error');
            return;
        }
        
        // Disable form
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<span class="spinner is-active"></span> در حال پردازش...');
        
        // AJAX request
        $.ajax({
            url: artaBudgetAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'arta_budget_update_credit',
                nonce: artaBudgetAdmin.nonce,
                user_id: userId,
                amount: amount,
                operation: operation,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    
                    // Update credit in table
                    var $row = $('tr[data-user-id="' + userId + '"]');
                    var newCredit = parseFloat(response.data.new_credit) || 0;
                    
                    $row.find('.arta-budget-credit-amount').text(formatNumber(newCredit));
                    $row.find('.arta-budget-btn-increase, .arta-budget-btn-decrease')
                        .data('current-credit', newCredit);
                    
                    // Close popup
                    closePopup($form.closest('.arta-budget-popup'));
                    
                    // Reset form
                    $form[0].reset();
                } else {
                    showMessage(response.data.message || 'خطایی رخ داد.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error');
                console.error('AJAX Error:', error);
            },
            complete: function() {
                // Re-enable form
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }
    
    /**
     * Load credit history via AJAX
     */
    function loadCreditHistory(userId) {
        $.ajax({
            url: artaBudgetAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'arta_budget_get_credit_history',
                nonce: artaBudgetAdmin.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success && response.data.history) {
                    var history = response.data.history;
                    var html = '';
                    
                    if (history.length > 0) {
                        html = '<table class="arta-budget-history-table">' +
                            '<thead>' +
                            '<tr>' +
                            '<th>تاریخ</th>' +
                            '<th>اعتبار قبلی</th>' +
                            '<th>اعتبار جدید</th>' +
                            '<th>مقدار تغییر</th>' +
                            '<th>نوع</th>' +
                            '<th>دلیل</th>' +
                            '</tr>' +
                            '</thead>' +
                            '<tbody>';
                        
                        history.forEach(function(item) {
                            var changeClass = item.is_positive ? 'arta-budget-history-positive' : 'arta-budget-history-negative';
                            var changeSign = item.is_positive ? '+' : '';
                            
                            html += '<tr>' +
                                '<td>' + item.date + '</td>' +
                                '<td>' + item.previous_credit + '</td>' +
                                '<td>' + item.new_credit + '</td>' +
                                '<td class="' + changeClass + '">' + changeSign + item.change_amount + '</td>' +
                                '<td>' + item.change_type + '</td>' +
                                '<td>' + (item.reason || '-') + '</td>' +
                                '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    } else {
                        html = '<div class="arta-budget-history-empty">' +
                            'تاریخچه‌ای برای این کاربر یافت نشد.' +
                            '</div>';
                    }
                    
                    $('#arta-budget-history-content').html(html);
                } else {
                    $('#arta-budget-history-content').html(
                        '<div class="arta-budget-history-empty">' +
                        'خطا در بارگذاری تاریخچه.' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $('#arta-budget-history-content').html(
                    '<div class="arta-budget-history-empty">' +
                    'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.' +
                    '</div>'
                );
                console.error('AJAX Error:', error);
            }
        });
    }
    
    /**
     * Show message
     */
    function showMessage(message, type) {
        var $message = $('#arta-budget-message');
        $message
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .fadeIn();
        
        setTimeout(function() {
            $message.fadeOut();
        }, 5000);
    }
    
    /**
     * Format number with thousand separators
     */
    function formatNumber(num) {
        var numStr = parseFloat(num).toFixed(2);
        var parts = numStr.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }
    
})(jQuery);
