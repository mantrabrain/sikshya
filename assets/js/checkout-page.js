/**
 * Checkout: quote totals (coupon) + start Sikshya checkout session (Stripe / PayPal) via REST.
 */
(function () {
  var root = document.getElementById('sikshya-checkout-root');
  if (!root || !root.dataset.restUrl) {
    return;
  }

  var cfg =
    typeof window.sikshyaCheckout === 'object' && window.sikshyaCheckout
      ? window.sikshyaCheckout
      : {};
  var i18n = cfg.i18n || {};
  function t(key, fb) {
    var v = i18n[key];
    return typeof v === 'string' && v !== '' ? v : fb;
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

  var subtotalEl = document.getElementById('sikshya-checkout-subtotal-display');
  var totalEl = document.getElementById('sikshya-checkout-total-value');
  var discountRow = document.getElementById('sikshya-checkout-discount-row');
  var discountDisplay = document.getElementById('sikshya-checkout-discount-display');
  var couponInput = document.getElementById('sikshya-checkout-coupon');
  var applyBtn = document.getElementById('sikshya-checkout-apply-coupon');

  function setStatus(msg) {
    if (statusEl) {
      statusEl.textContent = msg || '';
    }
  }

  function getCouponCode() {
    return couponInput ? String(couponInput.value).trim() : '';
  }

  function applyTotalsFromQuote(data) {
    var fmt = data.formatted || {};
    if (subtotalEl && fmt.subtotal) {
      subtotalEl.textContent = fmt.subtotal;
    }
    if (totalEl && fmt.total) {
      totalEl.textContent = fmt.total;
    }
    var disc = typeof data.discount === 'number' ? data.discount : parseFloat(data.discount);
    if (discountRow && discountDisplay) {
      if (!isNaN(disc) && disc > 0.00001 && fmt.discount) {
        discountDisplay.textContent = fmt.discount;
        discountRow.hidden = false;
      } else {
        discountDisplay.textContent = '';
        discountRow.hidden = true;
      }
    }
  }

  function fetchQuote(couponCode, onDone) {
    if (!courseIds.length) {
      setStatus(t('noCourses', 'No courses in checkout.'));
      if (onDone) {
        onDone();
      }
      return;
    }
    setStatus(t('updatingTotals', 'Updating totals…'));
    fetch(restUrl + 'checkout/quote', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
      },
      body: JSON.stringify({
        course_ids: courseIds,
        coupon_code: couponCode || '',
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.ok) {
          setStatus((json && json.message) || t('quoteFailed', 'Could not update totals.'));
          if (onDone) {
            onDone();
          }
          return;
        }
        applyTotalsFromQuote(json.data || {});
        setStatus('');
        if (onDone) {
          onDone();
        }
      })
      .catch(function () {
        setStatus(t('networkError', 'Network error. Please try again.'));
        if (onDone) {
          onDone();
        }
      });
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', function () {
      applyBtn.disabled = true;
      fetchQuote(getCouponCode(), function () {
        applyBtn.disabled = false;
      });
    });
  }

  if (couponInput && applyBtn) {
    couponInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (!applyBtn.disabled) {
          applyBtn.click();
        }
      }
    });
  }

  function startGateway(gateway) {
    if (!courseIds.length) {
      setStatus(t('noCourses', 'No courses in checkout.'));
      return;
    }
    setStatus(t('startingCheckout', 'Starting checkout…'));
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
        coupon_code: getCouponCode(),
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.ok) {
          setStatus((json && json.message) || t('checkoutFailed', 'Checkout failed.'));
          return;
        }
        var d = json.data || {};
        if (d.redirect_url) {
          window.location.href = d.redirect_url;
          return;
        }
        if (d.approval_url) {
          window.location.href = d.approval_url;
          return;
        }
        if (d.client_secret) {
          setStatus(
            t(
              'stripeSessionReady',
              'Payment session ready. Your site should confirm the Stripe PaymentIntent on return (see Sikshya checkout docs).'
            )
          );
          return;
        }
        setStatus(t('checkoutStarted', 'Checkout started.'));
      })
      .catch(function () {
        setStatus(t('networkError', 'Network error. Please try again.'));
      });
  }

  root.querySelectorAll('[data-sikshya-gateway]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var g = btn.getAttribute('data-sikshya-gateway');
      if (g === 'stripe' || g === 'paypal' || g === 'offline') {
        startGateway(g);
      }
    });
  });
})();
