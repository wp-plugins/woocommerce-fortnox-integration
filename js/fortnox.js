function sync_orders() {
    var data = {
        action: 'sync_orders'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många ordrar som ska exporteras. Ett meddelande visas på denna sida när synkroniseringen är klar.');

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function fetch_contacts() {
    var data = {
        action: 'fetch_contacts'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många kunder som ska importeras. Ett meddelande visas på denna sida när synkroniseringen är klar');

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function initial_sync_products() {
    var data = {
        action: 'initial_sync_products'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkter som ska exporteras. Ett meddelande visas på denna sida när synkroniseringen är klar.');
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function update_fortnox_inventory() {
    var data = {
        action: 'update_fortnox_inventory'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkters lagesaldo som ska importeras. Ett meddelande visas på denna sida när synkroniseringen är klar.');
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function send_support_mail() {
    var data = jQuery('form#support').serialize();
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}