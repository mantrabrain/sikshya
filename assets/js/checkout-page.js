/**
 * Checkout page: starts native Sikshya checkout session (Stripe / PayPal) via REST.
 */
(function () {
  var root = document.getElementById('sikshya-checkout-root');
  if (!root || !root.dataset.restUrl) {
    return;
  }

  var statusEl = document.getElementById('sikshya-checkout-status');
  var restUrl = root.dataset.restUrl.replace(/\/?$/, '/');
  var nonce = root.dataset.restNonce || '';
  var courseIds = [];
  try {
    courseIds = JSON.parse(root.dataset.courseIds || '[]');
  } catch (e) {
    courseIds = [];
  }

  function setStatus(msg) {
    if (statusEl) {
      statusEl.textContent = msg;
    }
  }

  function startGateway(gateway) {
    if (!courseIds.length) {
      setStatus('No courses in checkout.');
      return;
    }
    setStatus('Starting checkout…');
    fetch(restUrl + 'checkout/session', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
      },
      body: JSON.stringify({
        course_ids: courseIds,
        gateway: gateway,
        coupon_code: '',
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.ok) {
          setStatus((json && json.message) || 'Checkout failed.');
          return;
        }
        var d = json.data || {};
        if (d.approval_url) {
          window.location.href = d.approval_url;
          return;
        }
        if (d.client_secret) {
          setStatus(
            'Payment session ready. Your site should confirm the Stripe PaymentIntent on return (see Sikshya checkout docs).'
          );
          return;
        }
        setStatus('Checkout started.');
      })
      .catch(function () {
        setStatus('Network error.');
      });
  }

  root.querySelectorAll('[data-sikshya-gateway]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var g = btn.getAttribute('data-sikshya-gateway');
      if (g === 'stripe' || g === 'paypal') {
        startGateway(g);
      }
    });
  });
})();
