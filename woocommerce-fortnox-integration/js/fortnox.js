function sync_orders() {
    var data = {
        action: 'sync_orders'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många ordrar som ska exporteras. <br>Ett meddelande visas på denna sida när synkroniseringen är klar. Lämna ej denna sida, då avbryts exporten!');

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function fetch_contacts() {
    var data = {
        action: 'fetch_contacts'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många kunder som ska importeras. <br>Ett meddelande visas på denna sida när synkroniseringen är klar. Lämna ej denna sida, då avbryts importen!');

    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}

function initial_sync_products() {
    var data = {
        action: 'initial_sync_products'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkter som ska exporteras. <br>Ett meddelande visas på denna sida när synkroniseringen är klar. Lämna ej denna sida, då avbryts exporten!');
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}