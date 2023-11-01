jQuery(document).ready(function($) {
    $('#sortable').sortable();

    $('#save-tab-order').on('click', function(e) {
        e.preventDefault();
        
        var tabOrder = $('#sortable').sortable('toArray').toString();

        // Collect checked checkboxes
        var visibleTabs = [];
        $('#sortable input:checked').each(function() {
            visibleTabs.push($(this).val());
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wct_save_tab_order',
                order: tabOrder,
                visibility: visibleTabs,  // Added visibility to AJAX data
                nonce: $('#wct_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    displayNotice('Tab order saved successfully.', 'success');
                } else {
                    displayNotice('Error saving order.', 'error');
                }
            },
            error: function() {
                displayNotice('An error occurred. Please try again.', 'error');
            }
        });
    });

    function displayNotice(message, type) {
        var noticeClass = (type === 'success') ? 'notice-success' : 'notice-error';
        var noticeHTML = `
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
            </div>
        `;
        $('.wrap').prepend(noticeHTML);
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 3000);
    }
});
