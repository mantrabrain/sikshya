/**
 * Checkout: quote totals (coupon) + start Sikshya checkout session (Stripe / PayPal) via REST.
 */
(function () {
  var root = document.getElementById('sikshya-checkout-root');
  var cfgBoot =
    typeof window.sikshyaCheckoutConfig === 'object' && window.sikshyaCheckoutConfig ? window.sikshyaCheckoutConfig : {};
  if (!root || !cfgBoot || !cfgBoot.restUrl) {
    return;
  }

  var isLoggedIn = cfgBoot.isLoggedIn === true || cfgBoot.isLoggedIn === 1 || cfgBoot.isLoggedIn === '1';
  var guestEnabled = cfgBoot.guestEnabled === true || cfgBoot.guestEnabled === 1 || cfgBoot.guestEnabled === '1';
  var guestNonce = String(cfgBoot.guestNonce || '');

  var dfEnabled = false;
  var dfSchema = [];
  var dfPrefills = {};
  var dfCountries = {};
  if (cfgBoot.df && typeof cfgBoot.df === 'object') {
    dfEnabled = !!cfgBoot.df.enabled;
    dfSchema = Array.isArray(cfgBoot.df.schema) ? cfgBoot.df.schema : [];
    dfPrefills = cfgBoot.df.prefills && typeof cfgBoot.df.prefills === 'object' ? cfgBoot.df.prefills : {};
    dfCountries = cfgBoot.df.countries && typeof cfgBoot.df.countries === 'object' ? cfgBoot.df.countries : {};
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
  var errorsEl = document.getElementById('sikshya-checkout-errors');
  var restUrl = String(cfgBoot.restUrl || '').replace(/\/?$/, '/');
  var nonce = String(cfgBoot.restNonce || '');
  var courseIds = [];
  courseIds = Array.isArray(cfgBoot.courseIds) ? cfgBoot.courseIds : [];

  function getCheckoutFetchOptions(method) {
    var headers = {
      'Content-Type': 'application/json',
      'X-Sikshya-Guest-Nonce': guestNonce,
    };

    if (isLoggedIn && nonce) {
      headers['X-WP-Nonce'] = nonce;
      return { method: method, credentials: 'same-origin', headers: headers };
    }

    // Guest checkout: keep cookies so CartStorage works, but never send X-WP-Nonce.
    // WP will only enforce cookie nonce checks when an auth cookie exists.
    return { method: method, credentials: 'same-origin', headers: headers };
  }

  var subtotalEl = document.getElementById('sikshya-checkout-subtotal-display');
  var totalEl = document.getElementById('sikshya-checkout-total-value');
  var discountRow = document.getElementById('sikshya-checkout-discount-row');
  var discountDisplay = document.getElementById('sikshya-checkout-discount-display');
  var couponInput = document.getElementById('sikshya-checkout-coupon');
  var applyBtn = document.getElementById('sikshya-checkout-apply-coupon');
  var guestEmailEl = document.getElementById('sikshya-checkout-guest-email');
  var guestNameEl = document.getElementById('sikshya-checkout-guest-name');
  var dfHostGuest = document.getElementById('sikshya-checkout-dynamic-fields-guest');
  var dfHostAccount = document.getElementById('sikshya-checkout-dynamic-fields-account');
  var primaryActionBtn = document.getElementById('sikshya-checkout-primary-action');
  var gatewayDetailsEl = document.getElementById('sikshya-checkout-gateway-details');
  var gatewayUiEls = [];
  try {
    gatewayUiEls = Array.prototype.slice.call(root.querySelectorAll('[data-sikshya-gateway-ui]'));
  } catch (e) {
    gatewayUiEls = [];
  }

  var selectedGateway = '';
  var gatewayButtons = [];
  try {
    gatewayButtons = Array.prototype.slice.call(root.querySelectorAll('[data-sikshya-gateway]'));
  } catch (e) {
    gatewayButtons = [];
  }

  var dfValues = {};

  function dfSlug(s) {
    return String(s || '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9_]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 64);
  }

  function dfGetVal(id) {
    id = dfSlug(id);
    return typeof dfValues[id] === 'string' ? dfValues[id] : '';
  }

  function dfSetVal(id, v) {
    id = dfSlug(id);
    dfValues[id] = String(v || '');
  }

  function dfFieldVisible(field) {
    try {
      var vis = field && typeof field.visibility === 'object' ? field.visibility : null;
      if (!vis || !vis.depends_on) return true;
      var dep = dfSlug(vis.depends_on);
      var cur = dfGetVal(dep);
      if (typeof vis.depends_value === 'string' && vis.depends_value !== '') {
        return cur === String(vis.depends_value);
      }
      if (Array.isArray(vis.depends_in) && vis.depends_in.length) {
        return vis.depends_in.map(String).indexOf(cur) !== -1;
      }
      return true;
    } catch (e) {
      return true;
    }
  }

  function dfRenderInto(host) {
    if (!host) return;
    if (!dfEnabled || !dfSchema.length) {
      host.innerHTML = '';
      return;
    }

    var html = '';
    html += '<div class="sikshya-checkout-df">';
    html += '<h3 class="sikshya-checkout-df__title">' + t('additionalInfo', 'Additional information') + '</h3>';
    html += '<div class="sikshya-checkout-df__grid">';
    dfSchema.forEach(function (f) {
      var id = dfSlug(f && f.id ? f.id : '');
      if (!id) return;
      if (f && f.enabled === false) return;
      if (f && f.system) return;
      var type = String((f && f.type) || 'text');
      var label = String((f && f.label) || id);
      var help = String((f && f.help) || '');
      var ph = String((f && f.placeholder) || '');
      var required = !!(f && f.required);
      var visible = dfFieldVisible(f);
      var val = dfGetVal(id);
      var width = String((f && f.width) || '');
      var span2 = width === 'full' || type === 'textarea' || type === 'checkbox';

      var fieldClass = 'sikshya-checkout-df__field';
      if (span2) {
        fieldClass += ' sikshya-checkout-df__field--full';
      }
      html += '<div data-df-field="' + id + '" class="' + fieldClass + '"' + (visible ? '' : ' style="display:none;"') + '>';

      var reqStar = required
        ? ' <span class="sikshya-checkout-field__required" aria-hidden="true">*</span>'
        : '';

      if (type === 'checkbox') {
        html += '<label class="sikshya-checkout-df__checkbox" for="sikshya-df-' + id + '">';
        html +=
          '<input type="checkbox" class="sikshya-checkout-df__checkbox-input" id="sikshya-df-' +
          id +
          '" data-df-input="' +
          id +
          '" ' +
          (val === '1' ? 'checked' : '') +
          ' />';
        html += '<span class="sikshya-checkout-df__checkbox-text">' + label + reqStar + '</span>';
        html += '</label>';
      } else {
        html +=
          '<label class="sikshya-checkout-field__label" for="sikshya-df-' + id + '">' +
          label +
          reqStar +
          '</label>';
        if (type === 'textarea') {
          html +=
            '<textarea id="sikshya-df-' +
            id +
            '" data-df-input="' +
            id +
            '" rows="4" class="sikshya-input sikshya-checkout-field__control sikshya-checkout-field__control--textarea">' +
            (val || '') +
            '</textarea>';
        } else if (type === 'country') {
          html +=
            '<select id="sikshya-df-' +
            id +
            '" data-df-input="' +
            id +
            '" class="sikshya-input sikshya-checkout-field__control">';
          html += '<option value="">' + t('chooseCountry', 'Choose country…') + '</option>';
          try {
            Object.keys(dfCountries || {}).forEach(function (code) {
              var name = dfCountries[code];
              if (!code || !name) return;
              var c = String(code);
              var n = String(name);
              html += '<option value="' + c.replace(/"/g, '&quot;') + '" ' + (val === c ? 'selected' : '') + '>' + n + '</option>';
            });
          } catch (e) {
            /* ignore */
          }
          html += '</select>';
        } else if (type === 'select' || type === 'radio') {
          var opts = Array.isArray(f && f.options ? f.options : null) ? f.options : [];
          if (type === 'select') {
            html +=
              '<select id="sikshya-df-' +
              id +
              '" data-df-input="' +
              id +
              '" class="sikshya-input sikshya-checkout-field__control">';
            html += '<option value="">' + t('chooseOne', 'Choose…') + '</option>';
            opts.forEach(function (o) {
              var ov = o && o.value !== undefined ? String(o.value) : '';
              var ol = o && o.label !== undefined ? String(o.label) : ov;
              html += '<option value="' + ov.replace(/"/g, '&quot;') + '" ' + (val === ov ? 'selected' : '') + '>' + ol + '</option>';
            });
            html += '</select>';
          } else {
            html += '<div class="sikshya-checkout-df__radio-stack" role="radiogroup">';
            opts.forEach(function (o, idx) {
              var ov = o && o.value !== undefined ? String(o.value) : '';
              var ol = o && o.label !== undefined ? String(o.label) : ov;
              var rid = 'sikshya-df-' + id + '-' + idx;
              html +=
                '<label class="sikshya-checkout-df__radio-row" for="' +
                rid +
                '">' +
                '<input type="radio" name="sikshya-df-radio-' +
                id +
                '" id="' +
                rid +
                '" data-df-input="' +
                id +
                '" value="' +
                ov.replace(/"/g, '&quot;') +
                '" ' +
                (val === ov ? 'checked' : '') +
                ' />' +
                '<span>' +
                ol +
                '</span>' +
                '</label>';
            });
            html += '</div>';
          }
        } else {
          var inputType = type === 'email' ? 'email' : type === 'tel' ? 'tel' : type === 'number' ? 'number' : 'text';
          html +=
            '<input id="sikshya-df-' +
            id +
            '" data-df-input="' +
            id +
            '" type="' +
            inputType +
            '" class="sikshya-input sikshya-checkout-field__control" placeholder="' +
            (ph || '').replace(/"/g, '&quot;') +
            '" value="' +
            (val || '').replace(/"/g, '&quot;') +
            '" />';
        }
      }
      if (help) {
        html += '<p class="sikshya-checkout-df__help">' + help + '</p>';
      }
      html += '</div>';
    });
    html += '</div></div>';

    host.innerHTML = html;

    // Bind input listeners to keep dfValues updated and re-apply visibility.
    try {
      host.querySelectorAll('[data-df-input]').forEach(function (el) {
        var id = el.getAttribute('data-df-input') || '';
        var isCheckbox = el.type === 'checkbox';
        var isRadio = el.type === 'radio';
        var handler = function () {
          if (isCheckbox) {
            dfSetVal(id, el.checked ? '1' : '0');
          } else if (isRadio) {
            if (el.checked) dfSetVal(id, el.value || '');
          } else {
            dfSetVal(id, el.value || '');
          }
          dfApplyVisibility(host);
        };
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
      });
    } catch (e) {
      /* ignore */
    }

    dfApplyVisibility(host);
  }

  function dfApplyVisibility(host) {
    if (!host) return;
    dfSchema.forEach(function (f) {
      var id = dfSlug(f && f.id ? f.id : '');
      if (!id) return;
      if (f && f.enabled === false) return;
      if (f && f.system) return;
      var wrap = host.querySelector('[data-df-field="' + id + '"]');
      if (!wrap) return;
      var visible = dfFieldVisible(f);
      wrap.style.display = visible ? '' : 'none';
    });
  }

  function dfInit() {
    if (!dfEnabled || !dfSchema.length) return;
    // Prefill known values (logged-in) and set defaults for checkbox false.
    dfSchema.forEach(function (f) {
      var id = dfSlug(f && f.id ? f.id : '');
      if (!id) return;
      if (f && f.enabled === false) return;
      if (f && f.system) return;
      if (dfPrefills && typeof dfPrefills[id] === 'string' && dfPrefills[id] !== '') {
        dfSetVal(id, dfPrefills[id]);
      } else if (String((f && f.type) || '') === 'checkbox') {
        dfSetVal(id, '0');
      } else {
        dfSetVal(id, '');
      }
    });
    dfRenderInto(dfHostGuest);
    dfRenderInto(dfHostAccount);
  }

  function dfCollectVisibleValues(host) {
    var out = {};
    if (!dfEnabled || !dfSchema.length || !host) return out;
    dfSchema.forEach(function (f) {
      var id = dfSlug(f && f.id ? f.id : '');
      if (!id) return;
      if (f && f.enabled === false) return;
      if (f && f.system) return;
      var wrap = host.querySelector('[data-df-field="' + id + '"]');
      if (wrap && wrap.style && wrap.style.display === 'none') {
        return;
      }
      var v = dfGetVal(id);
      out[id] = v;
    });
    return out;
  }

  function dfValidateRequired(host) {
    if (!dfEnabled || !dfSchema.length || !host) return true;
    var firstMissing = null;
    for (var i = 0; i < dfSchema.length; i++) {
      var f = dfSchema[i];
      var id = dfSlug(f && f.id ? f.id : '');
      if (!id) continue;
      if (f && f.enabled === false) continue;
      if (f && f.system) continue;
      var wrap = host.querySelector('[data-df-field="' + id + '"]');
      if (wrap && wrap.style && wrap.style.display === 'none') continue;
      if (f && f.required) {
        var v = dfGetVal(id);
        if (!v) {
          firstMissing = id;
          break;
        }
      }
    }
    if (firstMissing) {
      showError(t('missingRequired', 'Please complete all required fields to continue.'));
      try {
        var el = host.querySelector('[data-df-input="' + firstMissing + '"]');
        if (el && el.focus) el.focus();
      } catch (e) {
        /* ignore */
      }
      return false;
    }
    return true;
  }

  // Auth tabs (Sign in / Create account) on checkout.
  (function initAuthTabs() {
    var tabButtons = root.querySelectorAll('[data-sikshya-auth-tab]');
    var panels = root.querySelectorAll('[data-sikshya-auth-panel]');
    if (!tabButtons.length || !panels.length) {
      return;
    }

    function setActive(tab) {
      panels.forEach(function (p) {
        var id = p.getAttribute('data-sikshya-auth-panel') || '';
        p.hidden = id !== tab;
      });
      tabButtons.forEach(function (b) {
        var id = b.getAttribute('data-sikshya-auth-tab') || '';
        if (id === tab) {
          b.classList.remove('sikshya-btn--ghost');
          b.classList.add('sikshya-btn--primary');
        } else {
          b.classList.remove('sikshya-btn--primary');
          b.classList.add('sikshya-btn--ghost');
        }
      });
    }

    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tab = btn.getAttribute('data-sikshya-auth-tab') || 'login';
        setActive(tab);
      });
    });

    // Default tab: guest checkout (if present) else login — unless URL requests auth flash tab first.
    var hasGuestPanel = false;
    panels.forEach(function (p) {
      if ((p.getAttribute('data-sikshya-auth-panel') || '') === 'guest') {
        hasGuestPanel = true;
      }
    });

    var flashScope = '';
    try {
      var u0 = new URL(window.location.href);
      flashScope = u0.searchParams.get('sikshya_auth_scope') || '';
    } catch (e0) {
      flashScope = '';
    }

    if (flashScope === 'login' || flashScope === 'register') {
      setActive(flashScope);
    } else {
      setActive(hasGuestPanel ? 'guest' : 'login');
    }

    // Strip ?sikshya_auth_*= from address bar after choosing tab (message is already in the HTML).
    try {
      var u = new URL(window.location.href);
      var scope = u.searchParams.get('sikshya_auth_scope') || '';
      if (scope && u.searchParams.get('sikshya_auth_message')) {
        u.searchParams.delete('sikshya_auth_scope');
        u.searchParams.delete('sikshya_auth_message');
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', u.pathname + u.search + u.hash);
        }
      }
    } catch (e) {
      /* ignore */
    }
  })();

  function getGuestEmail() {
    return guestEmailEl ? String(guestEmailEl.value || '').trim() : '';
  }

  function getGuestName() {
    // Guest name is optional and now handled via Dynamic Fields (Growth) if desired.
    return guestNameEl ? String(guestNameEl.value || '').trim() : '';
  }

  function requireGuestEmailOrShowError() {
    if (isLoggedIn) return true;
    if (!guestEnabled) {
      showError(t('signInToCheckout', 'Please sign in to continue checkout.'));
      return false;
    }
    var email = getGuestEmail();
    if (!email || email.indexOf('@') === -1) {
      showError(t('guestEmailInvalid', 'Please enter a valid email address to continue.'));
      if (guestEmailEl && guestEmailEl.focus) guestEmailEl.focus();
      return false;
    }
    return true;
  }

  // Dynamic checkout fields
  dfInit();

  function confirmReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      var isPayPalReturn = u.searchParams.get('sikshya_paypal_return') === '1';
      var orderId = parseInt(u.searchParams.get('order_id') || '0', 10);
      var token = u.searchParams.get('token') || ''; // PayPal order id
      var publicToken = u.searchParams.get('public_token') || '';
      if (!isPayPalReturn || !orderId || !token) {
        return;
      }
      clearError();
      setStatus(t('confirmingPayment', 'Confirming payment…'));
      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: getCheckoutFetchOptions('POST').credentials,
        headers: getCheckoutFetchOptions('POST').headers,
        body: JSON.stringify({
          order_id: orderId,
          gateway: 'paypal',
          paypal_order_id: token,
          public_token: publicToken,
        }),
      })
        .then(function (r) {
          return r.json().then(function (j) {
            return { ok: r.ok, status: r.status, json: j };
          });
        })
        .then(function (res) {
          if (res.ok && res.json && res.json.ok && res.json.data && res.json.data.redirect_url) {
            clearCartThenRedirect(res.json.data.redirect_url);
            return;
          }
          showError((res.json && res.json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          showError(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      /* ignore */
    }
  }

  function clearCartThenRedirect(redirectUrl) {
    try {
      fetch(restUrl + 'checkout/clear-cart', {
        method: 'POST',
        credentials: getCheckoutFetchOptions('POST').credentials,
        headers: getCheckoutFetchOptions('POST').headers,
        body: JSON.stringify({}),
      })
        .then(function () {
          window.location.href = redirectUrl;
        })
        .catch(function () {
          window.location.href = redirectUrl;
        });
    } catch (e) {
      window.location.href = redirectUrl;
    }
  }

  function setStatus(msg) {
    if (statusEl) {
      statusEl.textContent = msg || '';
    }
  }

  function clearError() {
    if (!errorsEl) return;
    errorsEl.hidden = true;
    errorsEl.innerHTML = '';
  }

  function showError(msg) {
    setStatus('');
    if (!errorsEl) {
      setStatus(msg || '');
      return;
    }
    var safe = String(msg || '').trim();
    if (!safe) {
      clearError();
      return;
    }
    errorsEl.hidden = false;
    errorsEl.innerHTML =
      '<p class="sikshya-checkout-errors__title">' +
      t('checkoutErrorTitle', 'Checkout error') +
      '</p>' +
      '<p class="sikshya-checkout-errors__text"></p>';
    var p = errorsEl.querySelector('.sikshya-checkout-errors__text');
    if (p) p.textContent = safe;
    if (errorsEl.scrollIntoView) {
      errorsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
      showError(t('noCourses', 'No courses in checkout.'));
      if (onDone) {
        onDone();
      }
      return;
    }
    if (!isLoggedIn && !guestEnabled) {
      showError(t('signInToCheckout', 'Please sign in to continue checkout.'));
      if (onDone) onDone();
      return;
    }
    clearError();
    setStatus(t('updatingTotals', 'Updating totals…'));
    fetch(restUrl + 'checkout/quote', {
      method: 'POST',
      credentials: getCheckoutFetchOptions('POST').credentials,
      headers: getCheckoutFetchOptions('POST').headers,
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
          showError((json && json.message) || t('quoteFailed', 'Could not update totals.'));
          if (onDone) {
            onDone();
          }
          return;
        }
        applyTotalsFromQuote(json.data || {});
        try {
          var persisted = typeof couponCode === 'string' ? couponCode.trim() : '';
          if (persisted) {
            window.sessionStorage.setItem('sikshya_checkout_coupon', persisted);
          } else {
            window.sessionStorage.removeItem('sikshya_checkout_coupon');
          }
        } catch (e) {
          /* ignore */
        }
        setStatus('');
        if (onDone) {
          onDone();
        }
      })
      .catch(function () {
        showError(t('networkError', 'Network error. Please try again.'));
        if (onDone) {
          onDone();
        }
      });
  }

  var canUseCouponUi = isLoggedIn || guestEnabled;

  if (applyBtn && canUseCouponUi) {
    applyBtn.addEventListener('click', function () {
      applyBtn.disabled = true;
      fetchQuote(getCouponCode(), function () {
        applyBtn.disabled = false;
      });
    });
  }

  if (couponInput && applyBtn && canUseCouponUi) {
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
    var savedCoupon = window.sessionStorage.getItem('sikshya_checkout_coupon');
    if (couponInput && savedCoupon && canUseCouponUi && !String(couponInput.value || '').trim()) {
      couponInput.value = savedCoupon;
      fetchQuote(savedCoupon, function () {});
    }
  } catch (e) {
    /* ignore */
  }

  function collectBillingFromDom() {
    var out = {
      phone: '',
      address_1: '',
      address_2: '',
      city: '',
      state: '',
      postcode: '',
      country: '',
    };
    try {
      var scope = root || document;
      function pick(sel) {
        var el = scope.querySelector(sel);
        if (!el) {
          return '';
        }
        return String(el.value || '').trim();
      }
      out.phone = pick('#sikshya-checkout-billing-phone');
      out.address_1 = pick('#sikshya-checkout-billing-address-1');
      out.address_2 = pick('#sikshya-checkout-billing-address-2');
      out.city = pick('#sikshya-checkout-billing-city');
      out.state = pick('#sikshya-checkout-billing-state');
      out.postcode = pick('#sikshya-checkout-billing-postcode');
      out.country = pick('#sikshya-checkout-billing-country');
    } catch (e) {
      /* ignore */
    }
    return out;
  }

  function startGateway(gateway) {
    if (!courseIds.length) {
      showError(t('noCourses', 'No courses in checkout.'));
      return;
    }
    if (!requireGuestEmailOrShowError()) {
      return;
    }
    // Validate required dynamic fields (if enabled).
    var dfHost = !isLoggedIn ? dfHostGuest : dfHostAccount;
    if (dfHost && !dfValidateRequired(dfHost)) {
      return;
    }
    clearError();
    setStatus(t('startingCheckout', 'Starting checkout…'));
    var marketingOptInEl = document.getElementById('sikshya-checkout-marketing-opt-in');
    var marketingOptIn = marketingOptInEl ? Boolean(marketingOptInEl.checked) : null;
    var billingPayload = isLoggedIn ? collectBillingFromDom() : {};
    fetch(restUrl + 'checkout/session', {
      method: 'POST',
      credentials: getCheckoutFetchOptions('POST').credentials,
      headers: getCheckoutFetchOptions('POST').headers,
      body: JSON.stringify({
        course_ids: courseIds,
        gateway: gateway,
        coupon_code: getCouponCode(),
        marketing_opt_in: marketingOptIn,
        guest_email: !isLoggedIn ? getGuestEmail() : '',
        guest_name: !isLoggedIn ? getGuestName() : '',
        dynamic_fields: dfHost ? dfCollectVisibleValues(dfHost) : {},
        billing: billingPayload,
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.ok) {
          showError((json && json.message) || t('checkoutFailed', 'Checkout failed.'));
          if (json && json.code === 'email_exists') {
            // Switch to sign-in tab if present.
            try {
              var loginBtn = root.querySelector('[data-sikshya-auth-tab=\"login\"]');
              if (loginBtn && loginBtn.click) loginBtn.click();
            } catch (e) {
              /* ignore */
            }
          }
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
              'Payment session ready. Complete payment on Stripe, or use Pay with card if your theme loads Stripe.js.'
            )
          );
          return;
        }
        setStatus(t('checkoutStarted', 'Checkout started.'));
      })
      .catch(function () {
        showError(t('networkError', 'Network error. Please try again.'));
      });
  }

  function setGatewaySelection(g) {
    selectedGateway = String(g || '');
    gatewayButtons.forEach(function (b) {
      try {
        var bg = b.getAttribute('data-sikshya-gateway') || '';
        if (!bg) return;
        if (bg === selectedGateway) {
          b.classList.add('is-selected');
          b.setAttribute('aria-pressed', 'true');
        } else {
          b.classList.remove('is-selected');
          b.setAttribute('aria-pressed', 'false');
        }
      } catch (e) {
        /* ignore */
      }
    });

    if (primaryActionBtn) {
      primaryActionBtn.setAttribute('data-sikshya-primary-gateway', selectedGateway);
      // Keep button label stable (don’t copy huge card text); use first line only.
      var label = '';
      gatewayButtons.some(function (b) {
        if ((b.getAttribute('data-sikshya-gateway') || '') !== selectedGateway) return false;
        try {
          var meta = b.querySelector('.sikshya-checkout-gateways__label');
          label = meta && meta.textContent ? meta.textContent.trim() : '';
        } catch (e) {
          label = '';
        }
        return true;
      });
      if (label) {
        primaryActionBtn.textContent = label;
      }
    }

    if (gatewayUiEls && gatewayUiEls.length) {
      gatewayUiEls.forEach(function (el) {
        try {
          var forG = el.getAttribute('data-sikshya-gateway-ui') || '';
          el.hidden = forG !== selectedGateway;
        } catch (e) {
          /* ignore */
        }
      });
      if (gatewayDetailsEl) {
        gatewayDetailsEl.hidden = true;
        gatewayDetailsEl.innerHTML = '';
      }
    } else if (gatewayDetailsEl) {
      var reason = '';
      var configured = '1';
      gatewayButtons.some(function (b) {
        if ((b.getAttribute('data-sikshya-gateway') || '') !== selectedGateway) return false;
        reason = b.getAttribute('data-sikshya-gateway-disabled-reason') || '';
        configured = b.getAttribute('data-sikshya-gateway-configured') || '1';
        return true;
      });

      var title = t('paymentDetails', 'Payment details');
      var text = '';
      if (reason && configured !== '1') {
        text = reason;
      } else if (selectedGateway === 'offline' || selectedGateway === 'bank_transfer') {
        text = t(
          'offlineCheckoutHint',
          'You will place the order first. Payment instructions will appear on your receipt page.'
        );
      } else if (selectedGateway === 'paypal') {
        text = t('paypalHint', 'You will be redirected to PayPal to complete payment securely.');
      } else if (selectedGateway === 'stripe') {
        text = t('stripeHint', 'You will be redirected to Stripe’s secure checkout to enter card details.');
      } else {
        text = t('gatewayHint', 'Continue to the secure payment step for this gateway.');
      }

      gatewayDetailsEl.innerHTML =
        '<div class="sikshya-checkout-gateway-details__title">' +
        title +
        '</div><p class="sikshya-checkout-gateway-details__text">' +
        String(text || '') +
        '</p>';
      gatewayDetailsEl.hidden = false;
    }
  }

  gatewayButtons.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();
      var g = btn.getAttribute('data-sikshya-gateway');
      if (g) {
        setGatewaySelection(g);
      }
    });
  });

  if (primaryActionBtn) {
    primaryActionBtn.addEventListener('click', function () {
      var g = primaryActionBtn.getAttribute('data-sikshya-primary-gateway') || selectedGateway || '';
      if (!g) {
        // Fall back to first gateway button.
        try {
          var first = root.querySelector('[data-sikshya-gateway]');
          if (first) {
            g = first.getAttribute('data-sikshya-gateway') || '';
          }
        } catch (e) {
          /* ignore */
        }
      }
      if (g) {
        // If Stripe inline checkout is enabled, Pro must handle the pay action.
        // This prevents the core Stripe redirect flow from triggering.
        try {
          function stripeInlineEnabled() {
            return !!(cfgBoot && cfgBoot.stripe_inline && cfgBoot.stripe_inline.enabled === true);
          }
          function tryPayOnce() {
            return (
              window.sikshyaStripeInlineCheckout &&
              typeof window.sikshyaStripeInlineCheckout.pay === 'function' &&
              (window.sikshyaStripeInlineCheckout.pay(), true)
            );
          }
          if (g === 'stripe' && stripeInlineEnabled()) {
            // If the inline bundle is present but execution is delayed (defer/optimizer),
            // wait briefly for `pay()` to appear instead of showing a false "did not load".
            if (tryPayOnce()) return;
            var boot = window.sikshyaStripeInlineCheckout || null;
            var bootstrapped = !!(boot && boot._bootstrapped);
            var startedAt = Date.now();
            var maxWaitMs = 2500;
            (function waitForPay() {
              if (tryPayOnce()) return;
              if (Date.now() - startedAt >= maxWaitMs) {
                showError(
                  bootstrapped
                    ? t(
                        'stripeInlineStillLoading',
                        'Stripe inline checkout is taking too long to initialize. Please disable JS optimization/minify for checkout and try again.'
                      )
                    : t(
                        'stripeInlineMissing',
                        'Stripe inline checkout is enabled, but the inline payment script did not load. Please check if it is blocked (404/optimizer) and try again.'
                      )
                );
                return;
              }
              setTimeout(waitForPay, 100);
            })();
            return;
          }
        } catch (e) {
          // ignore and fall through to normal gateway start
        }
        startGateway(g);
      }
    });
  }

  // Initial selection: first enabled, non-disabled gateway button.
  if (!selectedGateway) {
    var firstOk = '';
    gatewayButtons.some(function (b) {
      if (b.hasAttribute('disabled')) return false;
      var g = b.getAttribute('data-sikshya-gateway') || '';
      if (g) {
        firstOk = g;
        return true;
      }
      return false;
    });
    if (!firstOk && gatewayButtons.length) {
      firstOk = gatewayButtons[0].getAttribute('data-sikshya-gateway') || '';
    }
    if (firstOk) {
      setGatewaySelection(firstOk);
    }
  }

  // Handle PayPal redirect return by capturing + enrolling, then redirecting to receipt.
  confirmReturnIfNeeded();

  // Pro-only gateway confirm flows (Stripe / Mollie / Paystack / Razorpay) are registered by Sikshya Pro.
  // Core exposes a tiny runtime API so Pro can show status/errors and redirect on success.
  try {
    window.sikshyaCheckoutRuntime = window.sikshyaCheckoutRuntime || {
      t: t,
      setStatus: setStatus,
      showError: showError,
      clearError: clearError,
      clearCartThenRedirect: clearCartThenRedirect,
      restUrl: restUrl,
      nonce: nonce,
      guestNonce: guestNonce,
    };
  } catch (e) {
    /* ignore */
  }
  try {
    var extra = window.sikshyaCheckoutReturnConfirmers;
    if (Array.isArray(extra)) {
      extra.forEach(function (fn) {
        try {
          if (typeof fn === 'function') fn(window.sikshyaCheckoutRuntime);
        } catch (e) {
          /* ignore */
        }
      });
    }
  } catch (e) {
    /* ignore */
  }

})();
