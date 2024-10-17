jQuery(document).ready(function ($) {
    $('#add-merchant-pair-button').on('click', function () {
        var merchantID = $('#new-merchant-id').val();
        var merchantName = $('#new-merchant-name').val();

        if (merchantID && merchantName) {
            $.ajax({
                url: ajaxurl,  
                method: 'POST',
                data: {
                    action: 'mcwc_add_merchant_pair',
                    merchant_id: merchantID,
                    merchant_name: merchantName,
                    security: mappingTable.nonce  
                },
                success: function (response) {
                    if (response.success) {
                        $('#merchant-pairs-table').append(
                            '<tr data-id="' + merchantID + '">' +
                            '<td>' + merchantID + '</td>' +
                            '<td>' + merchantName + '</td>' +
                            '<td><button class="button delete-merchant-pair" data-id="' + merchantID + '">' + mappingTable.deleteText + '</button></td>' +
                            '</tr>'
                        );

                        $('#new-merchant-id').val('');
                        $('#new-merchant-name').val('');
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        } else {
            alert("Please enter both Monek ID and Merchant Name.");
        }
    });

    $('#merchant-pairs-table').on('click', '.delete-merchant-pair', function () {
        var row = $(this).closest('tr');
        var merchantID = $(this).data('id');

        $.ajax({
            url: ajaxurl, 
            method: 'POST',
            data: {
                action: 'mcwc_delete_merchant_pair',
                merchant_id: merchantID,
                security: mappingTable.nonce
            },
            success: function (response) {
                if (response.success) {
                    row.remove();
                } else {
                    alert(response.data.message);
                }
            },
            failure: function (response) {
                alert("Failed to delete merchant pair.");
            }
        });
    });
});
