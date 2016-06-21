loadScript('https://checkout.stripe.com/checkout.js',
           function() {

  var handler = StripeCheckout.configure({
    key: '{{ @STRIPE_KEY }}',
    image: '{{ @STATIC }}/images/wonton-logo-128x128.jpg',
    token: function(token) {
      // Use the token to create the charge with a server-side script.
      // You can access the token ID with `token.id`

      var data= $('#order').serialize();
      var tok= JSON.stringify(token);

      $.ajax(BASE + 'saveRegistration',
             { type : 'POST',
               data : data + '&token=' + tok + '&amount=' + total } )
            .done(function (data) {
              window.location.href= BASE + 'registration-confirmed';
            })
            .fail(function (jqxhr, textStatus, error) {
              var err= error;
              alert(error);
            });

    }
  });

  $('#purchase').on('click', function(e) {
    e.preventDefault();

    // Get total number of people and work out total
    var people= 0;
    $('input#people').each(function() {
      people+= Number($(this).val());
    });

    if (people == 0) {
      alert("You have to tell us how many people are coming.");
      return;
    }

    total= (people * 1000);

    // Open Checkout with further options
    handler.open({
      name: 'Raw Materials Art Supplies',
      description: people + (people > 1 ? ' people' : ' person'),
      email: $('#order #email').val(),
      zipCode: true,
      amount: total
    });
  });

  $('#order').on('submit', function(e) {
    // XXX write better error message
    alert("Sorry, something is wrong with checking out.");
    e.preventDefault();
  });

  // Close Checkout on page navigation
  $(window).on('popstate', function() {
    handler.close();
  });

});
