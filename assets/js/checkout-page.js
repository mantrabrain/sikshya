/**
 * Checkout: quote totals (coupon) + start Sikshya checkout session (Stripe / PayPal) via REST.
 */
(function () {
  var root = document.getElementById('sikshya-checkout-root');
  var cfgBoot =
    typeof window.sikshyaCheckoutConfig === 'object' && window.sikshyaCheckoutConfig ? window.sikshyaCheckoutConfig : {};
  var bootRestUrl = String(cfgBoot.restUrl || '');
  var dsRestUrl = root && root.dataset ? String(root.dataset.restUrl || '') : '';
  if (!root || (!bootRestUrl && !dsRestUrl)) {
    return;
  }

  var isLoggedIn = cfgBoot.isLoggedIn === true || cfgBoot.isLoggedIn === 1 || cfgBoot.isLoggedIn === '1';
  if (!cfgBoot || typeof cfgBoot.isLoggedIn === 'undefined') {
    isLoggedIn = String(root.dataset.isLoggedIn || '') === '1';
  }
  var guestEnabled = cfgBoot.guestEnabled === true || cfgBoot.guestEnabled === 1 || cfgBoot.guestEnabled === '1';
  if (!cfgBoot || typeof cfgBoot.guestEnabled === 'undefined') {
    guestEnabled = String(root.dataset.guestEnabled || '') === '1';
  }
  var guestNonce = String(cfgBoot.guestNonce || '') || String(root.dataset.guestNonce || '');

  var dfEnabled = false;
  var dfSchema = [];
  var dfPrefills = {};
  var dfCountries = {};
  if (cfgBoot.df && typeof cfgBoot.df === 'object') {
    dfEnabled = !!cfgBoot.df.enabled;
    dfSchema = Array.isArray(cfgBoot.df.schema) ? cfgBoot.df.schema : [];
    dfPrefills = cfgBoot.df.prefills && typeof cfgBoot.df.prefills === 'object' ? cfgBoot.df.prefills : {};
    dfCountries = cfgBoot.df.countries && typeof cfgBoot.df.countries === 'object' ? cfgBoot.df.countries : {};
  } else {
    dfEnabled = String(root.dataset.dfEnabled || '') === '1';
    try {
      dfSchema = JSON.parse(root.dataset.dfSchema || '[]');
      if (!Array.isArray(dfSchema)) dfSchema = [];
    } catch (e) {
      dfSchema = [];
    }
    try {
      dfPrefills = JSON.parse(root.dataset.dfPrefills || '{}') || {};
    } catch (e) {
      dfPrefills = {};
    }
    try {
      dfCountries = JSON.parse(root.dataset.dfCountries || '{}') || {};
    } catch (e) {
      dfCountries = {};
    }
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
  var restUrl = String(cfgBoot.restUrl || root.dataset.restUrl || '').replace(/\/?$/, '/');
  var nonce = String(cfgBoot.restNonce || root.dataset.restNonce || '');
  var courseIds = [];
  if (Array.isArray(cfgBoot.courseIds)) {
    courseIds = cfgBoot.courseIds;
  } else {
    try {
      courseIds = JSON.parse(root.dataset.courseIds || '[]');
    } catch (e) {
      courseIds = [];
    }
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
    html += '<div class="sikshya-checkout-df" style="margin-top:0.25rem;">';
    html += '<h3 style="margin:0 0 0.5rem;font-size:0.95rem;">' + t('additionalInfo', 'Additional information') + '</h3>';
    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">';
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

      html += '<div data-df-field="' + id + '" style="' + (span2 ? 'grid-column:1 / -1;' : '') + (visible ? '' : 'display:none;') + '">';
      if (type === 'checkbox') {
        html += '<label style="display:flex;gap:0.5rem;align-items:flex-start;font-weight:600;margin:0;">';
        html +=
          '<input type="checkbox" id="sikshya-df-' +
          id +
          '" data-df-input="' +
          id +
          '" ' +
          (val === '1' ? 'checked' : '') +
          ' />' +
          '<span>' +
          label +
          (required ? ' *' : '') +
          '</span>';
        html += '</label>';
      } else {
        html += '<label style="display:block;font-weight:600;margin:0 0 0.25rem;" for="sikshya-df-' + id + '">' + label + (required ? ' *' : '') + '</label>';
        if (type === 'textarea') {
          html +=
            '<textarea id="sikshya-df-' +
            id +
            '" data-df-input="' +
            id +
            '" rows="3" class="sikshya-input" style="width:100%;">' +
            (val || '') +
            '</textarea>';
        } else if (type === 'country') {
          html += '<select id="sikshya-df-' + id + '" data-df-input="' + id + '" class="sikshya-input" style="width:100%;">';
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
            html += '<select id="sikshya-df-' + id + '" data-df-input="' + id + '" class="sikshya-input" style="width:100%;">';
            html += '<option value="">' + t('chooseOne', 'Choose…') + '</option>';
            opts.forEach(function (o) {
              var ov = o && o.value !== undefined ? String(o.value) : '';
              var ol = o && o.label !== undefined ? String(o.label) : ov;
              html += '<option value="' + ov.replace(/"/g, '&quot;') + '" ' + (val === ov ? 'selected' : '') + '>' + ol + '</option>';
            });
            html += '</select>';
          } else {
            html += '<div style="display:grid;gap:0.5rem;">';
            opts.forEach(function (o, idx) {
              var ov = o && o.value !== undefined ? String(o.value) : '';
              var ol = o && o.label !== undefined ? String(o.label) : ov;
              var rid = 'sikshya-df-' + id + '-' + idx;
              html +=
                '<label for="' +
                rid +
                '" style="display:flex;gap:0.5rem;align-items:flex-start;margin:0;">' +
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
            '" class="sikshya-input" style="width:100%;" placeholder="' +
            (ph || '').replace(/"/g, '&quot;') +
            '" value="' +
            (val || '').replace(/"/g, '&quot;') +
            '" />';
        }
      }
      if (help) {
        html += '<div style="margin-top:0.25rem;font-size:12px;opacity:0.8;">' + help + '</div>';
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
      setStatus(t('missingRequired', 'Please complete all required fields to continue.'));
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

    // Default tab: guest checkout (if present) else login.
    var hasGuestPanel = false;
    panels.forEach(function (p) {
      if ((p.getAttribute('data-sikshya-auth-panel') || '') === 'guest') {
        hasGuestPanel = true;
      }
    });
    setActive(hasGuestPanel ? 'guest' : 'login');
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
      showError('Please sign in to continue checkout.');
      return false;
    }
    var email = getGuestEmail();
    if (!email || email.indexOf('@') === -1) {
      showError('Please enter a valid email to continue.');
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
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
          'X-Sikshya-Guest-Nonce': guestNonce,
        },
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
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
          'X-Sikshya-Guest-Nonce': guestNonce,
        },
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
      setStatus(t('noCourses', 'No courses in checkout.'));
      if (onDone) {
        onDone();
      }
      return;
    }
    if (!isLoggedIn && !guestEnabled) {
      showError('Please sign in to continue checkout.');
      if (onDone) onDone();
      return;
    }
    clearError();
    setStatus(t('updatingTotals', 'Updating totals…'));
    fetch(restUrl + 'checkout/quote', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
        'X-Sikshya-Guest-Nonce': guestNonce,
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
          showError((json && json.message) || t('quoteFailed', 'Could not update totals.'));
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
        showError(t('networkError', 'Network error. Please try again.'));
        if (onDone) {
          onDone();
        }
      });
  }

  if (applyBtn && isLoggedIn) {
    applyBtn.addEventListener('click', function () {
      applyBtn.disabled = true;
      fetchQuote(getCouponCode(), function () {
        applyBtn.disabled = false;
      });
    });
  }

  if (couponInput && applyBtn && isLoggedIn) {
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
    fetch(restUrl + 'checkout/session', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
        'X-Sikshya-Guest-Nonce': guestNonce,
      },
      body: JSON.stringify({
        course_ids: courseIds,
        gateway: gateway,
        coupon_code: getCouponCode(),
        marketing_opt_in: marketingOptIn,
        guest_email: !isLoggedIn ? getGuestEmail() : '',
        guest_name: !isLoggedIn ? getGuestName() : '',
        dynamic_fields: dfHost ? dfCollectVisibleValues(dfHost) : {},
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

  // Handle Stripe redirect return by confirming + enrolling, then redirecting to receipt.
  confirmStripeReturnIfNeeded();

  confirmMollieReturnIfNeeded();
  confirmPaystackReturnIfNeeded();
  confirmRazorpayReturnIfNeeded();

  function confirmStripeReturnIfNeeded() {
    try {
      var u = new URL(window.location.href);
      if (u.searchParams.get('sikshya_stripe_cancel') === '1') {
        setStatus(t('stripeCancelled', 'Stripe checkout was cancelled. You can choose a payment method again.'));
        return;
      }
      var checkoutSessionId = u.searchParams.get('checkout_session_id') || '';
      var explicitOrderId = parseInt(u.searchParams.get('order_id') || '0', 10) || 0;
      var publicToken = u.searchParams.get('public_token') || '';

      if (u.searchParams.get('sikshya_stripe_return') === '1' && checkoutSessionId && explicitOrderId) {
        clearError();
        setStatus(t('stripeConfirming', 'Finalizing Stripe payment…'));
        fetch(restUrl + 'checkout/confirm', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
            'X-Sikshya-Guest-Nonce': guestNonce,
          },
          body: JSON.stringify({
            gateway: 'stripe',
            order_id: explicitOrderId,
            checkout_session_id: checkoutSessionId,
            public_token: publicToken,
          }),
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (json) {
            if (!json || !json.ok) {
              showError((json && json.message) || t('stripeConfirmFailed', 'Could not confirm Stripe payment.'));
              return;
            }
            try {
              window.sessionStorage.removeItem('sikshya_stripe_checkout');
            } catch (e) {
              // ignore
            }
            var d = json.data || {};
            if (d.redirect_url) {
              clearCartThenRedirect(d.redirect_url);
              return;
            }
            setStatus(t('stripeConfirmed', 'Payment confirmed.'));
          })
          .catch(function () {
            showError(t('networkError', 'Network error. Please try again.'));
          });
        return;
      }

      var redirectStatus = u.searchParams.get('redirect_status') || '';
      var pi = u.searchParams.get('payment_intent') || '';
      var piSecret = u.searchParams.get('payment_intent_client_secret') || '';

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
        showError(t('stripeMissingOrder', 'Stripe returned successfully, but order context is missing.'));
        return;
      }

      clearError();
      setStatus(t('stripeConfirming', 'Finalizing Stripe payment…'));

      fetch(restUrl + 'checkout/confirm', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
          'X-Sikshya-Guest-Nonce': guestNonce,
        },
        body: JSON.stringify({
          gateway: 'stripe',
          order_id: orderId,
          payment_intent_id: pi,
          client_secret: piSecret || '',
          public_token: publicToken,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          if (!json || !json.ok) {
            showError((json && json.message) || t('stripeConfirmFailed', 'Could not confirm Stripe payment.'));
            return;
          }
          try {
            window.sessionStorage.removeItem('sikshya_stripe_checkout');
          } catch (e) {
            // ignore
          }
          var d = json.data || {};
          if (d.redirect_url) {
            clearCartThenRedirect(d.redirect_url);
            return;
          }
          setStatus(t('stripeConfirmed', 'Payment confirmed.'));
        })
        .catch(function () {
          showError(t('networkError', 'Network error. Please try again.'));
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
      clearError();
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
            clearCartThenRedirect(json.data.redirect_url);
            return;
          }
          showError((json && json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          showError(t('networkError', 'Network error. Please try again.'));
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
      clearError();
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
            clearCartThenRedirect(json.data.redirect_url);
            return;
          }
          showError((json && json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          showError(t('networkError', 'Network error. Please try again.'));
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
      clearError();
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
            clearCartThenRedirect(json.data.redirect_url);
            return;
          }
          showError((json && json.message) || t('confirmFailed', 'Could not confirm payment.'));
        })
        .catch(function () {
          showError(t('networkError', 'Network error. Please try again.'));
        });
    } catch (e) {
      /* ignore */
    }
  }
})();
