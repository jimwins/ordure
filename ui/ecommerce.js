<check if="{{ @GOOGLE_TAG_MANAGER }}">

  // push promotions into dataLayer
  let promos= document.querySelectorAll('[data-ec-promo-name]')
  if (promos.length) {
    let items= [];

    promos.forEach((promo) => {
      let name= promo.getAttribute('data-ec-promo-name')
      let item= promo.getAttribute('data-ec-item-id')
      let location= promo.getAttribute('data-ec-location-id')

      items.push({
        'item_id': item,
        'promotion_name': name,
        'location_id': location
      })
    })

    dataLayer.push({ ecommerce: null });
    dataLayer.push({
      'event': 'view_promotion',
      'ecommerce': {
        'items': items
      }
    })
  }

  let recordAddToCart= (ev) => {
    let name= "";
    let brand= "";
    let id= "P000";
    let price= "0.00";
    let variant= "0";
    let quantity= 1;

    debugger

    if (ev.target['name']) {
      name= ev.target['name'].value;
    } else if (ev.target.closest('[data-product-name]')) {
      name= ev.target.closest('[data-product-name]').getAttribute('data-product-name')
    }

    if (ev.target['product_id']) {
      id= ev.target['product_id'].value;
    } else if (ev.target.closest('[data-product]')) {
      id= ev.target.closest('[data-product]').getAttribute('data-product')
    }

    if (ev.target['sale_price']) {
      price= ev.target['sale_price'].value;
    } else if (ev.target.closest('[data-price]')) {
      price= ev.target.closest('[data-price]').getAttribute('data-price')
    }

    if (ev.target['brand']) {
      brand= ev.target['brand'].value;
    } else if (ev.target.closest('[data-brand]')) {
      brand= ev.target.closest('[data-brand]').getAttribute('data-brand')
    }

    if (ev.target['item']) {
      variant= ev.target['item'].value;
    }

    if (ev.target['quantity']) {
      quantity= ev.target['quantity'].value;
    }

    dataLayer.push({
      'event': 'eec.add',
      'ecommerce': {
        'add': {
          'products': [{
            'name': name,
            'id': 'P' + id,
            'price': price,
            'brand': brand,
            'variant': variant,
            'quantity': quantity,
          }]
        }
      }
    });
    <check if="{{ @FACEBOOK_PIXEL }}">
      fbq('track', 'AddToCart', {
        'content_type': 'product',
        'contents': [{
          'id': variant,
          'quantity': quantity,
        }]
      });
    </check>
    <check if="{{ @MICROSOFT_UET_ID }}">
      window.uetq.push('event', 'add_to_cart', {
              'ecomm_prodid': 'P' + id,
              'ecomm_pagetype': 'product',
              'ecomm_totalvalue': price * quantity,
              'revenue_value': price * quantity,
              'currency': 'USD',
              'items': [
                  {
                    'id': variant,
                    'quantity': quantity,
                    'price': price
                  }
                ]
              })
    </check>
  }

  document.querySelectorAll('form.add-item').forEach((form) => {
    form.addEventListener('submit', recordAddToCart)
  })
</check>
