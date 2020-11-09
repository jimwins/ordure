loadScript('https://js.stripe.com/v3/',
           function() {
  var stripe= Stripe('{{ @STRIPE_KEY }}');
  var elements= stripe.elements();
  var button= document.getElementById('stripe-button');
  var error= document.getElementById('stripe-error');

  var card= elements.create("card");
  // Stripe injects an iframe into the DOM
  card.mount("#card-element");

  card.on("change", function (event) {
    // Disable the Pay button if there are no card details in the Element
    button.disabled= event.empty;
    error.textContent= event.error ? event.error.message : ''
    if (event.error) error.classList.remove('hidden')
  });

  var form= document.getElementById("payment-form");
  form.addEventListener("submit", function(event) {
    event.preventDefault();
      return fetch('/sale/{{ @sale.uuid }}/get-stripe-payment-intent', {
        // fake AJAX header so we get JSON errors
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(res) {
        if (!res.ok) {
          return res.json().then((data) => {
            if (data.text == 'Payment already completed.') {
              window.location.href= "/sale/{{ @sale.uuid }}/thanks"
            } else {
              return Promise.reject(new Error(data.text))
            }
          })
        }
        return res.json()
      })
      .then(function(data) {
        payWithCard(stripe, card, data.secret);
      })
  });

  var payWithCard= function(stripe, card, clientSecret) {
    loading(true);
    stripe
      .confirmCardPayment(clientSecret, {
        payment_method: {
          card: card,
          billing_details: {
            name: '{{ @sale.name }}',
            email: '{{ @sale.email }}',
          }
        }
      })
      .then(function(result) {
        if (result.error) {
          // Show error to your customer
          showError(result.error.message);
        } else {
          // The payment succeeded!
    debugger
          orderComplete(result.paymentIntent.id);
        }
      });
  };

  var orderComplete= function(paymentIntentId) {
    return fetch('/sale/{{ @sale.uuid }}/process-stripe-payment', {
      method: 'POST',
    }).then(function (data) {
      if (data.ok) {
        reportPurchase()
        window.location.href= "/sale/{{ @sale.uuid }}/thanks"
      }
    });
  };

  // Show the customer the error from Stripe if their card fails to charge
  var showError= function(errorMsgText) {
    loading(false);
    error.textContent= errorMsgText;
    error.classList.remove('hidden');
    setTimeout(function() {
      error.textContent= "";
      error.classList.add('hidden');
    }, 4000);
  };

  // Show a spinner on payment submission
  var loading = function(isLoading) {
    if (isLoading) {
      // Disable the button and show a spinner
      button.disabled= true;
      button.querySelector(".spin").classList.remove("hidden");
    } else {
      button.disabled= false;
      button.querySelector(".spin").classList.add("hidden");
    }
  };

});

loadScript('https://www.paypal.com/sdk/js?client-id={{ @PAYPAL_CLIENT_ID }}',
           function() {
  paypal.Buttons({
    createOrder: function (data, actions) {
      return fetch('/sale/{{ @sale.uuid }}/get-paypal-order', {
        // fake AJAX header so we get JSON errors
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(res) {
        if (!res.ok) {
          return res.json().then((data) => {
            if (data.text == 'Payment already completed.') {
              window.location.href= "/sale/{{ @sale.uuid }}/thanks"
            } else {
              return Promise.reject(new Error(data.text))
            }
          })
        }
        return res.json()
      })
      .then(function(data) {
        return data.id
      })
    },
    onApprove: function(data, actions) {
      // Capture the funds from the transaction
      // XXX show processing...
      return actions.order.capture().then(function(details) {
        let formData= new FormData(document.getElementById('paypal-form'))
        formData.append('order_id', details.id)
        return fetch('/sale/{{ @sale.uuid }}/process-paypal-payment', {
          method: 'post',
          body: formData
        }).then(function (data) {
          if (data.ok) {
            reportPurchase()
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

$("#rewards-use").on("click", function (ev) {
  var $btn= $(ev.target);

  $btn.prop('disabled', true);

  $.ajax({ dataType: 'json', method: 'POST',
           url: "/sale/{{ @sale.uuid }}/process-rewards" })
   .done(function (data) {
     if (data.paid) {
       window.location.href= "/sale/{{ @sale.uuid }}/thanks";
     } else {
       window.location.href= "/sale/{{ @sale.uuid }}/pay";
     }
   })
   .fail(function (jqXHR, textStatus, errorThrown) {
     window.alert(jqXHR.responseJSON.text ?  jqXHR.responseJSON.text : textStatus);
   });

  return false;
});

if (document.getElementById('other-use')) {
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
}

function reportPurchase() {
  try {
    <check if="{{ @sale }}">
      gtag('event', 'purchase', {
        "transaction_id": "{{ @sale.uuid }}",
        "affiliation": "Online Store",
        "value": {{ @sale.total }},
        "currency": "USD",
        "tax": {{ @sale.tax }},
        "shipping": {{ @sale.shipping }},
        "items": [
          <repeat group="{{ @items }}"
                  value="{{ @item }}" counter="{{ @index }}">
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
  } catch (error) {
    console.error(error)
  }
}
