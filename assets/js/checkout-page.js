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

  function confirmReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      var isPayPalReturn = u.searchParams.get('sikshya_paypal_return') === '1';
      var orderId = parseInt(u.searchParams.get('order_id') || '0', 10);
      var token = u.searchParams.get('token') || ''; // PayPal order id
      if (!isPayPalReturn || !orderId || !token) {
        return;
      }
      setStatus(t('confirmingPayment', 'Confirming payment…'));
      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          order_id: orderId,
          gateway: 'paypal',
          paypal_order_id: token,
        }),
      })
        .then(function (r) {
          return r.json().then(function (j) {
            return { ok: r.ok, status: r.status, json: j };
          });
        })
        .then(function (res) {
          if (res.ok && res.json && res.json.ok && res.json.data && res.json.data.redirect_url) {
            window.location.href = res.json.data.redirect_url;
            return;
          }
          setStatus((res.json && res.json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          setStatus(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      /* ignore */
    }
  }

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
    var marketingOptInEl = document.getElementById('sikshya-checkout-marketing-opt-in');
    var marketingOptIn = marketingOptInEl ? Boolean(marketingOptInEl.checked) : null;
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
        marketing_opt_in: marketingOptIn,
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
          try {
            // Persist context so we can confirm the order on Stripe redirect return.
            window.sessionStorage.setItem(
              'sikshya_stripe_checkout',
              JSON.stringify({
                order_id: d.order_id || 0,
                payment_intent_id: d.payment_intent_id || '',
                client_secret: d.client_secret || '',
                at: Date.now(),
              })
            );
          } catch (e) {
            // ignore storage failures
          }
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
      if (g) {
        startGateway(g);
      }
    });
  });

  // Handle PayPal redirect return by capturing + enrolling, then redirecting to receipt.
  confirmReturnIfNeeded();

  // Handle Stripe redirect return by confirming + enrolling, then redirecting to receipt.
  confirmStripeReturnIfNeeded();

  confirmMollieReturnIfNeeded();
  confirmPaystackReturnIfNeeded();
  confirmRazorpayReturnIfNeeded();

  function confirmStripeReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      var redirectStatus = u.searchParams.get('redirect_status') || '';
      var pi = u.searchParams.get('payment_intent') || '';
      var piSecret = u.searchParams.get('payment_intent_client_secret') || '';
      var explicitOrderId = parseInt(u.searchParams.get('order_id') || '0', 10) || 0;

      if (!pi || redirectStatus !== 'succeeded') {
        return;
      }

      var stored = null;
      try {
        stored = JSON.parse(window.sessionStorage.getItem('sikshya_stripe_checkout') || 'null');
      } catch (e) {
        stored = null;
      }
      var storedOrderId = stored && stored.order_id ? parseInt(stored.order_id, 10) || 0 : 0;
      var orderId = explicitOrderId || storedOrderId;
      if (!orderId) {
        setStatus(t('stripeMissingOrder', 'Stripe returned successfully, but order context is missing.'));
        return;
      }

      setStatus(t('stripeConfirming', 'Finalizing Stripe payment…'));

      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          gateway: 'stripe',
          order_id: orderId,
          payment_intent_id: pi,
          client_secret: piSecret || '',
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          if (!json || !json.ok) {
            setStatus((json && json.message) || t('stripeConfirmFailed', 'Could not confirm Stripe payment.'));
            return;
          }
          try {
            window.sessionStorage.removeItem('sikshya_stripe_checkout');
          } catch (e) {
            // ignore
          }
          var d = json.data || {};
          if (d.redirect_url) {
            window.location.href = d.redirect_url;
            return;
          }
          setStatus(t('stripeConfirmed', 'Payment confirmed.'));
        })
        .catch(function () {
          setStatus(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      // ignore parsing errors
    }
  }

  function confirmMollieReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      if (u.searchParams.get('sikshya_mollie_return') !== '1') {
        return;
      }
      var orderId = parseInt(u.searchParams.get('order_id') || '0', 10) || 0;
      var paymentId = u.searchParams.get('id') || '';
      if (!orderId) {
        return;
      }
      setStatus(t('confirmingPayment', 'Confirming payment…'));
      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          order_id: orderId,
          gateway: 'mollie',
          mollie_payment_id: paymentId,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          if (json && json.ok && json.data && json.data.redirect_url) {
            window.location.href = json.data.redirect_url;
            return;
          }
          setStatus((json && json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          setStatus(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      /* ignore */
    }
  }

  function confirmPaystackReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      if (u.searchParams.get('sikshya_paystack_return') !== '1') {
        return;
      }
      var orderId = parseInt(u.searchParams.get('order_id') || '0', 10) || 0;
      var ref = u.searchParams.get('reference') || u.searchParams.get('trxref') || '';
      if (!orderId || !ref) {
        return;
      }
      setStatus(t('confirmingPayment', 'Confirming payment…'));
      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          order_id: orderId,
          gateway: 'paystack',
          paystack_reference: ref,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          if (json && json.ok && json.data && json.data.redirect_url) {
            window.location.href = json.data.redirect_url;
            return;
          }
          setStatus((json && json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          setStatus(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      /* ignore */
    }
  }

  function confirmRazorpayReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      if (u.searchParams.get('sikshya_razorpay_return') !== '1') {
        return;
      }
      var orderId = parseInt(u.searchParams.get('order_id') || '0', 10) || 0;
      if (!orderId) {
        return;
      }
      setStatus(t('confirmingPayment', 'Confirming payment…'));
      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          order_id: orderId,
          gateway: 'razorpay',
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          if (json && json.ok && json.data && json.data.redirect_url) {
            window.location.href = json.data.redirect_url;
            return;
          }
          setStatus((json && json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          setStatus(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      /* ignore */
    }
  }
})();
