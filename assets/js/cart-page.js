/**
 * Cart: preview totals with a coupon via checkout/quote REST (same server cart as checkout).
 */
(function () {
  var cfg = typeof window.sikshyaCartConfig === 'object' && window.sikshyaCartConfig ? window.sikshyaCartConfig : null;
  if (!cfg || !cfg.restUrl) {
    return;
  }

  var wrap = document.getElementById('sikshya-cart-coupon-wrap');
  if (!wrap) {
    return;
  }

  var restUrl = String(cfg.restUrl || '').replace(/\/?$/, '/');
  var guestNonce = String(cfg.guestNonce || '');
  var isLoggedIn = cfg.isLoggedIn === true || cfg.isLoggedIn === 1 || cfg.isLoggedIn === '1';
  var guestEnabled = cfg.guestEnabled === true || cfg.guestEnabled === 1 || cfg.guestEnabled === '1';
  var nonce = String(cfg.restNonce || '');
  var i18n = cfg.i18n && typeof cfg.i18n === 'object' ? cfg.i18n : {};

  function t(key, fb) {
    var v = i18n[key];
    return typeof v === 'string' && v !== '' ? v : fb;
  }

  var couponInput = document.getElementById('sikshya-cart-coupon');
  var applyBtn = document.getElementById('sikshya-cart-apply-coupon');
  var msgEl = document.getElementById('sikshya-cart-coupon-message');
  var subtotalEl = document.getElementById('sikshya-cart-subtotal-display');
  var discountRow = document.getElementById('sikshya-cart-discount-row');
  var discountDisplay = document.getElementById('sikshya-cart-discount-display');
  var totalEl = document.getElementById('sikshya-cart-total-display');

  function getCheckoutFetchOptions(method) {
    var headers = {
      'Content-Type': 'application/json',
      'X-Sikshya-Guest-Nonce': guestNonce,
    };
    if (isLoggedIn && nonce) {
      headers['X-WP-Nonce'] = nonce;
      return { method: method, credentials: 'same-origin', headers: headers };
    }
    return { method: method, credentials: 'same-origin', headers: headers };
  }

  function getCouponCode() {
    return couponInput ? String(couponInput.value).trim() : '';
  }

  function setMsg(text, isError) {
    if (!msgEl) {
      return;
    }
    if (!text) {
      msgEl.hidden = true;
      msgEl.textContent = '';
      msgEl.classList.remove('sikshya-cart-page__coupon-message--error');
      return;
    }
    msgEl.hidden = false;
    msgEl.textContent = text;
    if (isError) {
      msgEl.classList.add('sikshya-cart-page__coupon-message--error');
    } else {
      msgEl.classList.remove('sikshya-cart-page__coupon-message--error');
    }
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
    if (!isLoggedIn && !guestEnabled) {
      setMsg(t('signInToQuote', 'Please sign in to apply a discount code.'), true);
      if (onDone) {
        onDone();
      }
      return;
    }
    setMsg(t('updatingTotals', 'Updating totals…'), false);
    fetch(restUrl + 'checkout/quote', {
      method: 'POST',
      credentials: getCheckoutFetchOptions('POST').credentials,
      headers: getCheckoutFetchOptions('POST').headers,
      body: JSON.stringify({
        course_ids: [],
        coupon_code: couponCode || '',
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.ok) {
          setMsg((json && json.message) || t('quoteFailed', 'Could not update totals.'), true);
          if (onDone) {
            onDone();
          }
          return;
        }
        applyTotalsFromQuote(json.data || {});
        var persisted = typeof couponCode === 'string' ? couponCode.trim() : '';
        try {
          if (persisted) {
            window.sessionStorage.setItem('sikshya_checkout_coupon', persisted);
          } else {
            window.sessionStorage.removeItem('sikshya_checkout_coupon');
          }
        } catch (e) {
          /* ignore */
        }
        if (persisted) {
          setMsg(t('cartCouponSaved', 'Totals updated. This code will appear on checkout.'), false);
        } else {
          setMsg('', false);
        }
        if (onDone) {
          onDone();
        }
      })
      .catch(function () {
        setMsg(t('networkError', 'Network error. Please try again.'), true);
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

  try {
    var saved = window.sessionStorage.getItem('sikshya_checkout_coupon');
    if (couponInput && saved && !getCouponCode()) {
      couponInput.value = saved;
      fetchQuote(saved, function () {});
    }
  } catch (e) {
    /* ignore */
  }
})();
