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

  function stripeResponseHandler(status, response) {
    // Grab the form:
    var $form = $('#payment-form');

    if (response.error) { // Problem!

      // Show the errors on the form:
      $form.find('.payment-errors').text(response.error.message);
      $form.find('.payment-errors').removeClass('hidden');
      $form.find('[type="submit"]').prop('disabled', false); // Re-enable submission

    } else { // Token was created!

      // Get the token ID:
      var token = response.id;

      // Insert the token ID into the form so it gets submitted to the server:
      $form.append($('<input type="hidden" name="stripeToken">').val(token));

      // Submit the form:
      $form.get(0).submit();
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
