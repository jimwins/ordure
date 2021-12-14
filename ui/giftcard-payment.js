loadScript('https://js.stripe.com/v3/',
           function() {
  var stripe= Stripe('{{ @STRIPE_KEY }}');
  var elements= stripe.elements();
  var button= document.getElementById('stripe-button');
  var error= document.getElementById('stripe-error');

  var card= elements.create("card");
  // Stripe injects an iframe into the DOM
  card.mount("#card-element");

  var form= document.getElementById("payment-form");

  function enoughDetails(form) {
    let amount= form.elements['amount'].value.replace(/^\$/, '')
    if (form.elements['name'].value != '' &&
        form.elements['email'].value != '' &&
        parseFloat(amount))
    {
      return true;
    }
    return false;
  }

  card.on("change", function (event) {
    // Disable the Pay button if there are info not complete
    button.disabled= !event.complete || !enoughDetails(form);
    button.querySelector('.text-label').textContent= button.disabled ? 'Complete Details Above' : 'Buy Gift Card'
    error.textContent= event.error ? event.error.message : ''
    if (event.error) error.classList.remove('hidden')
  });

  form.addEventListener("submit", function(event) {
    event.preventDefault();
    return getPaymentIntent(form).then(function(data) {
      payWithCard(stripe, card, data.secret);
    })
  });

  var getPaymentIntent= function(form) {
    let formData= new FormData(form)
    return fetch('/gift-card/get-stripe-payment-intent', {
      // fake AJAX header so we get JSON errors
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      method: 'POST',
      body: formData,
    }).then(function(res) {
      return res.json()
    })
  }

  var orderComplete= function(paymentIntentId) {
    let formData= new FormData(form)
    formData.set('payment_intent_id', paymentIntentId)
    return fetch('/gift-card/process-stripe-payment', {
      method: 'POST',
      body: formData
    }).then(function (data) {
      if (data.ok) {
        window.location.href= "/gift-card/thanks"
      }
    });
  };

  var payWithCard= function(stripe, card, clientSecret) {
    loading(true);
    stripe
      .confirmCardPayment(clientSecret, {
        payment_method: {
          card: card
        }
      })
      .then(function(result) {
        if (result.error) {
          // Show error to your customer
          showError(result.error.message);
        } else {
          // The payment succeeded!
          orderComplete(result.paymentIntent.id);
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
