loadScript('https://js.stripe.com/v3/',
           function() {
  var stripe= Stripe('{{ @STRIPE_KEY }}');
  var elements= stripe.elements();

  var style= {
    base: {
      lineHeight: '1.429'
    }
  };

  var card= elements.create('card', { style: style });

  card.mount('#card-element');

  card.addEventListener('change', function(event) {
    var displayError= document.getElementById('card-errors');
    if (event.error) {
      displayError.textContent= event.error.message;
      displayError.classList.remove('hidden');
    } else {
      displayError.textContent= '';
      displayError.classList.add('hidden');
    }
  });

  var form = document.getElementById('payment-form');
  form.addEventListener('submit', function(event) {
    event.preventDefault();

    stripe.createToken(card).then(function(result) {
      if (result.error) {
        // Inform the customer that there was an error.
        var errorElement= document.getElementById('card-errors');
        errorElement.textContent= result.error.message;
        errorElement.classList.remove('hidden');
      } else {
        // Send the token to your server.
        stripeTokenHandler(result.token);
      }
    });
  });

  function stripeTokenHandler(token) {
    // Insert the token ID into the form so it gets submitted to the server
    var form= document.getElementById('payment-form');

    // prevent double-submit
    if (form.disabled) return;
    form.disabled= true;

    var hiddenInput= document.createElement('input');
    hiddenInput.setAttribute('type', 'hidden');
    hiddenInput.setAttribute('name', 'stripeToken');
    hiddenInput.setAttribute('value', token.id);
    form.appendChild(hiddenInput);

    // Submit the form
    if (form.getAttribute('data-ajax')) {
      $.ajax({ dataType: 'json', method: 'POST',
               url: form.getAttribute('action'),
               data: { stripeToken: token.id }})
       .done(function (data) {
         <check if="{{ @sale }}">
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
        </check>
        window.location.href= "/sale/{{ @sale.uuid }}/thanks";
       })
       .fail(function (jqXHR, textStatus, errorThrown) {
         var displayError= document.getElementById('card-errors');
         displayError.textContent= jqXHR.responseJSON.text ?
                                   jqXHR.responseJSON.text :
                                   textStatus;
         displayError.classList.remove('hidden');
         form.disabled= false;
       });
    } else {
      form.submit();
    }
  }

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
         <check if="{{ @sale }}">
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
          </check>
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
           url: $form.attr('action'),
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

document.getElementById('other-use').addEventListener('submit', (ev) => {
  ev.preventDefault()

  var $form= $(ev.target);
  let formData= new FormData(ev.target);

  $form.find('[type="submit"]').prop('disabled', true);
  $form.find('.errors').addClass('hidden');

  $.ajax({ dataType: 'json', method: 'POST',
           url: $form.attr('action'),
           data: Object.fromEntries(formData)
  })
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
