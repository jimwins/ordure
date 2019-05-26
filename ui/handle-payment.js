loadScript('https://js.stripe.com/v2/',
           function() {

  Stripe.setPublishableKey('{{ @STRIPE_KEY }}');

  var $form = $('#payment-form');
  $form.submit(function(event) {
    // Disable the submit button to prevent repeated clicks:
    $form.find('[type="submit"]').prop('disabled', true);

    // Validate the amount so it makes sense
    var amount= $form.find('[name="amount"]').val().trim();
    amount= amount.replace(/^\$/, ''); // Remove leading $
    if (isNaN(+amount) || !(+amount)) {
      $form.find('.payment-errors').text("You must enter a valid amount for the gift card!");
      $form.find('.payment-errors').removeClass('hidden');
      $form.find('[type="submit"]').prop('disabled', false); // Re-enable submission
      return false;
    }

    // Request a token from Stripe:
    Stripe.card.createToken($form, stripeResponseHandler);

    // Prevent the form from being submitted:
    return false;
  });

  function stripeShowError($form, message) {
    $form.find('.payment-errors').text(message);
    $form.find('.payment-errors').removeClass('hidden');
    $form.find('[type="submit"]').prop('disabled', false);
  }

  function stripeResponseHandler(status, response) {
    var $form = $('#payment-form');
    $form.find('.payment-errors').addClass('hidden');

    if (response.error) {
      stripeShowError($form, response.error.message);
    } else {
      var token= response.id;
      // Insert the token ID into the form so it gets submitted to the server:
      $form.append($('<input type="hidden" name="stripeToken">').val(token));

      if ($form.data('ajax')) {
        $.ajax({ dataType: 'json', method: 'POST',
                 url: $form.attr('action'),
                 data: { stripeToken: token }})
         .done(function (data) {
          gtag('event', 'purchase', {
             "transaction_id": "{{ @sale.uuid }}",
             "affiliation": "Online Store",
             "value": {{ @sale.total }},
             "currency": "USD",
             "tax": {{ @sale.tax }},
             "shipping": {{ @sale.shipping }},
             "items": [
          <repeat group="{{ @items }}" value="{{ @item }}" counter="{{ @index }}">
          <check if="{{ @index > 1 }}">,</check>
          {
            'id': "p{{ @item.product_id }}",
            'name': "{{ @item.product_name }}",
            'brand': "{{ @item.brand_name }}",
            'variant': "{{ @item.code }}",
            'quantity': "{{ @item.quantity }}",
            'price': "{{ @item.sale_price }}",
          }
          </repeat>
            ]
          });
          window.location.href= "/sale/{{ @sale.uuid }}/thanks";
         })
         .fail(function (jqXHR, textStatus, errorThrown) {
           stripeShowError($form,
                           jqXHR.responseJSON.text ?
                           jqXHR.responseJSON.text : textStatus); 
         });

      } else {
        $form.get(0).submit();
      }
    }
  };

});

loadScript('https://www.paypal.com/sdk/js?client-id={{ @PAYPAL_CLIENT_ID }}',
           function() {
  paypal.Buttons({
    createOrder: function (data, actions) {
      return fetch('/sale/{{ @sale.uuid }}/get-paypal-order')
        .then(function(res) {
          return res.json()
        })
        .then(function(data) {
          return data.id
        })
    },
    onApprove: function(data, actions) {
      // Capture the funds from the transaction
      return actions.order.capture().then(function(details) {
        return fetch('/sale/{{ @sale.uuid }}/process-paypal-payment', {
          method: 'post',
          headers: {
            'content-type': 'application/x-www-form-urlencoded'
          },
          body: "order_id=" + details.id
        }).then(function (data) {
          // XXX error handling?
          gtag('event', 'purchase', {
             "transaction_id": "{{ @sale.uuid }}",
             "affiliation": "Online Store",
             "value": {{ @sale.total }},
             "currency": "USD",
             "tax": {{ @sale.tax }},
             "shipping": {{ @sale.shipping }},
             "items": [
          <repeat group="{{ @items }}" value="{{ @item }}" counter="{{ @index }}">
          <check if="{{ @index > 1 }}">,</check>
          {
            'id': "p{{ @item.product_id }}",
            'name': "{{ @item.product_name }}",
            'brand': "{{ @item.brand_name }}",
            'variant': "{{ @item.code }}",
            'quantity': "{{ @item.quantity }}",
            'price': "{{ @item.sale_price }}",
          }
          </repeat>
            ]
          });
          if (data.ok) {
            window.location.href= "/sale/{{ @sale.uuid }}/thanks"
          }
        });
      });
    }
  }).render('#paypal-button');
});

$("#giftcard-check").on("submit", function (ev) {
  var $form= $(ev.target);

  $form.find('[type="submit"]').prop('disabled', true);
  $form.find('.errors').addClass('hidden');

  var card= $('[name="giftcard"]', $form).val();

  $.ajax({ dataType: 'json', method: 'POST',
           url: 'get-giftcard-balance',
           data: { card: card } })
   .done(function (data) {
     $form.addClass('hidden');
     var $use= $('#giftcard-use');
     var amount= $use.find('[name="amount"]');
     var text= "Pay $" + Math.min(amount.val(), data.balance).toFixed(2);
     $use.find('button').text(text);
     $use.find('[name="balance"]').val('$' + data.balance);
     $use.removeClass('hidden');
   })
   .fail(function (jqXHR, textStatus, errorThrown) {
     $form.find('.errors').text(jqXHR.responseJSON.text ?
                                jqXHR.responseJSON.text : textStatus); 
     $form.find('.errors').removeClass('hidden');
     $form.find('[type="submit"]').prop('disabled', false);
   });

  return false;
});

$("#giftcard-use").on("submit", function (ev) {
  var $form= $(ev.target);

  $form.find('[type="submit"]').prop('disabled', true);
  $form.find('.errors').addClass('hidden');

  var card= $('#giftcard-check [name="giftcard"]').val();

  $.ajax({ dataType: 'json', method: 'POST',
           url: $form.attr('action'),
           data: { card: card }})
   .done(function (data) {
     if (data.paid) {
       window.location.href= "/sale/{{ @sale.uuid }}/thanks";
     } else {
       window.location.href= "/sale/{{ @sale.uuid }}/pay";
     }
   })
   .fail(function (jqXHR, textStatus, errorThrown) {
     $form.find('.errors').text(jqXHR.responseJSON.text ?
                                jqXHR.responseJSON.text : textStatus); 
     $form.find('.errors').removeClass('hidden');
     $form.find('[type="submit"]').prop('disabled', false);
   });

  return false;
});

$("#other-use").on("submit", function (ev) {
  var $form= $(ev.target);

  $form.find('[type="submit"]').prop('disabled', true);
  $form.find('.errors').addClass('hidden');

  $.ajax({ dataType: 'json', method: 'POST',
           url: $form.attr('action') })
   .done(function (data) {
     if (data.paid) {
       window.location.href= "/sale/{{ @sale.uuid }}/thanks";
     } else {
       window.location.href= "/sale/{{ @sale.uuid }}/pay";
     }
   })
   .fail(function (jqXHR, textStatus, errorThrown) {
     $form.find('.errors').text(jqXHR.responseJSON.text ?
                                jqXHR.responseJSON.text : textStatus); 
     $form.find('.errors').removeClass('hidden');
     $form.find('[type="submit"]').prop('disabled', false);
   });

  return false;
});
