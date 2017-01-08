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
           window.location.href= "./thanks";
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

  $("#generate-bitcoin-address").on("click", function (ev) {
    $(ev.target).prop('disabled', true);
    $.ajax({ dataType: 'json', method: 'POST',
             url: 'generate-bitcoin-address',
             data: { } })
     .done(function (data) {
       // Show details
       var bc= $('#bitcoin-details');
       $('[name="amount"]', bc).val((data.bitcoin_amount / 100000000) + ' BTC');
       $('[name="receiver"]', bc).val(data.receiver_address);
       $('.uri', bc).attr('href',data.bitcoin_uri);
       bc.removeClass('hidden');

       // Hide prompt
       $('#bitcoin-prompt').addClass('hidden');
       $(ev.target).prop('disabled', false); // hidden, so go ahead and enable

       Stripe.source.poll(data.source_id, data.source_client_secret,
                          function (status, source) {
         $.ajax({ dataType: 'json', method: 'POST',
                  url: 'process-bitcoin-payment',
                  data: { } })
          .done(function (data) {
            window.location.href= "./thanks";
          })
          .fail(function (jqXHR, textStatus, errorThrown) {
            alert(jqXHR.responseJSON.text ?
                    jqXHR.responseJSON.text : textStatus); 
            $('#bitcoin-prompt').removeClass('hidden');
            $('#bitcoin-details').addClass('hidden');
          });
       });
     })
     .fail(function (jqXHR, textStatus, errorThrown) {
       $(ev.target).prop('disabled', false);
     });

  });

});

loadScript('https://js.braintreegateway.com/web/3.6.2/js/client.min.js',
           function() {
  loadScript('https://js.braintreegateway.com/web/3.6.2/js/paypal.min.js',
             function() {

    var paypalButton= document.getElementById('paypal-button');

    // Create a Client component
    braintree.client.create({
      authorization: '{{ @VZERO_CLIENT_TOKEN }}'
    }, function (clientErr, clientInstance) {
      // Create PayPal component
      braintree.paypal.create({
        client: clientInstance
      }, function (err, paypalInstance) {
        paypalButton.addEventListener('click', function () {
          $('.paypal-prompt').addClass('hidden');
          $('.paypal-waiting').removeClass('hidden');
          // Tokenize here!
          paypalInstance.tokenize({
            flow: 'checkout', // Required
            amount: {{ @sale.total - @sale.due }}, // Required
            currency: 'USD', // Required
            useraction: 'commit',
            locale: 'en_US',
            enableShippingAddress: true,
            shippingAddressEditable: false,
            shippingAddressOverride: {
              recipientName: '{{ @shipping_address.name ? @shipping_address.name : @sale.name }}',
              line1: '{{ @shipping_address.address1 }}',
              line2: '{{ @shipping_address.address2 }}',
              city: '{{ @shipping_address.city }}',
              countryCode: 'US',
              postalCode: '{{ @shipping_address.zip5 }}',
              state: '{{ @shipping_address.state }}',
              phone: ''
            }
          }, function (err, tokenizationPayload) {
            // Tokenization complete
            if (err && err.code == 'PAYPAL_POPUP_CLOSED') {
              $('.paypal-waiting').addClass('hidden');
              $('.paypal-prompt').removeClass('hidden');
              return; // That's cool.
            }

            if (err) {
              $('.paypal-waiting').addClass('hidden');
              $('.paypal-prompt').removeClass('hidden');
              $('.paypal-errors').text(err.message).removeClass('hidden');
              return;
            }

            // Send tokenizationPayload.nonce to server
            $.ajax({ dataType: 'json', method: 'POST',
                     url: 'process-paypal-payment',
                     data: { nonce: tokenizationPayload.nonce }})
             .done(function (data) {
               window.location.href= "./thanks";
             })
             .fail(function (jqXHR, textStatus, errorThrown) {
               $('.paypal-waiting').addClass('hidden');
               $('.paypal-prompt').removeClass('hidden');
               $('.paypal-errors').text(jqXHR.responseJSON.text ?
                                        jqXHR.responseJSON.text : textStatus)
                                  .removeClass('hidden');
             });
            
          });
        });
      });
    });

  });
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
       window.location.href= "./thanks";
     } else {
       window.location.href= "./pay";
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
       window.location.href= "./thanks";
     } else {
       window.location.href= "./pay";
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
