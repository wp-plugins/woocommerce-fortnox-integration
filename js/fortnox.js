function sync_orders(nonce) {
    var data = {
        action: 'sync_orders',
        security: nonce
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många ordrar som ska exporteras. Ett meddelande visas på denna sida när synkroniseringen är klar.');

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    }, 'json');
}

function fetch_contacts(nonce) {
    var data = {
        action: 'fetch_contacts',
        security: nonce
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många kunder som ska importeras. Ett meddelande visas på denna sida när synkroniseringen är klar');

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function manual_sync_products(nonce) {
    var data = {
        action: 'manual_sync_products',
        security: nonce
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkter som ska exporteras. Ett meddelande visas på denna sida när synkroniseringen är klar.');
    jQuery.post(ajaxurl, data, function(response) {

        var products = JSON.parse(response);
        jQuery('#ajax-fortnox-notification').show();
        for (index = 0; index < products.length; ++index) {
            try{
                var resp = JSON.parse(sync_multi_product(products[index], nonce));
                jQuery('#ajax-fortnox-message').html('WooCommerce Fortnox: Synkar ' + (index + 1) + ' av ' + products.length);

                if(resp['success'] == false){
                    jQuery('#ajax-fortnox-notification').append('<p id="ajax-error-fortnox-message" class="error">Fel på produkt ' + products[index] + ': ' + resp['message'] + '</p>');
                }
            }
            catch(err) {

            }
        }
    });
}

function update_fortnox_inventory(nonce) {
    var data = {
        action: 'update_fortnox_inventory',
        security: nonce
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkters lagesaldo som ska importeras. Ett meddelande visas på denna sida när synkroniseringen är klar.');
    jQuery.post(ajaxurl, data, function(response) {

    });
}

function send_support_mail(nonce) {
    var data = jQuery('form#support').serialize();
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function missing_list(nonce) {
    var data = {
        action: 'missing_list',
        security: nonce
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkters lagesaldo som ska importeras. Ett meddelande visas på denna sida när synkroniseringen är klar.');
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function clean_sku(nonce) {
    var data = {
        action: 'clean_sku',
        security: nonce
    };
    alert('Ett meddelande visas på denna sida när operationen är klar.');
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function sync_all_orders(nonce) {
    var data = {
        action: 'sync_all_orders',
        security: nonce
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många ordrar som ska exporteras. Ett meddelande visas på denna sida när synkroniseringen är klar.');

    jQuery.post(ajaxurl, data, function(response) {
        var orders = JSON.parse(response);
        jQuery('#ajax-fortnox-notification').show();
        for (index = 0; index < orders.length; ++index) {
            try{
                var resp = JSON.parse(sync_multi_order(orders[index], nonce));
                jQuery('#ajax-fortnox-message').html('WooCommerce Fortnox: Synkroniserar order ' + (index + 1) + ' av ' + orders.length);

                if(resp['success'] == false){
                    jQuery('#ajax-fortnox-notification').append('<p id="ajax-error-fortnox-message" class="error">Fel på order ' + orders[index] + ': ' + resp['message'] + '</p>');
                }
            }
            catch(err) {

            }
        }
    });

}

function test_connection(nonce) {
    var data = {
        action: 'test_connection',
        security: nonce
    };

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function sync_order(orderId, nonce) {
    var data = {
        action: 'sync_order',
        security: nonce,
        order_id: orderId
    };
    jQuery.post(ajaxurl, data, function(response) {
        jQuery('#ajax-fortnox-notification').show();
        jQuery('#ajax-fortnox-message').html('WooCommerce Fortnox: ' + response['message']);

        jQuery('html,body').animate({scrollTop: jQuery('#ajax-fortnox-notification').offset().top - 100 });

        if(response['success'] == false){
            jQuery('#ajax-fortnox-notification')
                .removeClass('updated')
                .addClass('error');
            if(response['link']){
                jQuery('#ajax-fortnox-message').append('<a href="http://wp-plugs.com/woocommerce-fortnox/' + response['link'] + '"> Se info</a>');
            }

            window.setTimeout(function(){

                // Move to a new location or you can do something else
                window.location.reload();

            }, 5000);
        }

    }, 'json');
}

function sync_product(productId, nonce) {
    var data = {
        action: 'sync_product',
        security: nonce,
        product_id: productId
    };
    jQuery.post(ajaxurl, data, function(response) {
        jQuery('#ajax-fortnox-notification').show();
        jQuery('#ajax-fortnox-message').html('WooCommerce Fortnox: ' + response['message']);
        jQuery('html,body').animate({scrollTop: jQuery('#ajax-fortnox-notification').offset().top - 100 });
        if(response['success'] == false){
            jQuery('#ajax-fortnox-notification')
                .removeClass('updated')
                .addClass('error');
        }
        else{
            jQuery('#post-productId');
        }

    }, 'json');
}

function sync_multi_product(productId, nonce) {
    var data = {
        action: 'sync_product',
        security: nonce,
        product_id: productId
    };
    var ajax_response;
    ajax_response = jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        async:false
    });
    return ajax_response.responseText;
}

function sync_multi_order(orderId, nonce) {
    var data = {
        action: 'sync_order',
        security: nonce,
        order_id: orderId
    };
    var ajax_response;
    ajax_response = jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        async:false
    });
    return ajax_response.responseText;
}