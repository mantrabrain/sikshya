/**
 * Login / registration shortcodes: submit via admin-ajax, show inline messages, redirect on success.
 */
(function () {
  function t(strings, key, fb) {
    var s = strings && strings[key];
    return typeof s === 'string' && s !== '' ? s : fb;
  }

  function buildCfg(host) {
    var base =
      typeof window.sikshyaAuthPublic === 'object' && window.sikshyaAuthPublic ? window.sikshyaAuthPublic : {};
    var ds = host && host.dataset ? host.dataset : {};
    return {
      ajaxUrl: (ds.sikshyaAjaxUrl && String(ds.sikshyaAjaxUrl)) || base.ajaxUrl || '',
      loginAction: (ds.sikshyaLoginAction && String(ds.sikshyaLoginAction)) || base.loginAction || 'sikshya_ajax_auth_login',
      registerAction:
        (ds.sikshyaRegisterAction && String(ds.sikshyaRegisterAction)) || base.registerAction || 'sikshya_ajax_auth_register',
      strings: base.strings && typeof base.strings === 'object' ? base.strings : {},
    };
  }

  function ensureMessagesBox(form, host) {
    var root = host || form.closest('.sikshya-auth');
    if (!root) {
      root = form.parentElement;
    }
    if (!root) {
      return null;
    }
    var box = root.querySelector('.sikshya-auth__messages');
    if (!box) {
      box = document.createElement('div');
      box.className = 'sikshya-auth__messages';
      box.setAttribute('role', 'region');
      box.setAttribute('aria-live', 'polite');
      root.insertBefore(box, form);
    }
    return box;
  }

  function revealCheckoutAuthPanel(form) {
    var panel = form.closest('[data-sikshya-auth-panel]');
    if (!panel) {
      return;
    }
    var wrap = panel.closest('.sikshya-checkout-auth');
    if (!wrap) {
      return;
    }
    var tabId = panel.getAttribute('data-sikshya-auth-panel') || '';
    if (!tabId) {
      return;
    }
    var tabBtn = wrap.querySelector('[data-sikshya-auth-tab="' + tabId.replace(/"/g, '') + '"]');
    if (tabBtn && typeof tabBtn.click === 'function') {
      tabBtn.click();
    }
  }

  function setBusy(form, busy) {
    form.classList.toggle('sikshya-auth__form--busy', !!busy);
    form.setAttribute('aria-busy', busy ? 'true' : 'false');
    var btn = form.querySelector('.sikshya-auth__submit');
    if (btn) {
      btn.disabled = !!busy;
    }
  }

  function clearMessages(box) {
    if (!box) {
      return;
    }
    box.innerHTML = '';
    box.hidden = true;
  }

  function showMessage(box, text, type, strings) {
    if (!box) {
      return;
    }
    var msg = String(text || '').trim();
    if (!msg) {
      msg = t(strings, 'requestFailed', 'Something went wrong. Please try again.');
    }
    box.hidden = false;
    box.removeAttribute('hidden');
    var cls = 'sikshya-notice sikshya-notice--error';
    if (type === 'success') {
      cls = 'sikshya-notice sikshya-notice--success';
    }
    var div = document.createElement('div');
    div.className = cls;
    div.setAttribute('role', 'alert');
    div.textContent = msg;
    box.innerHTML = '';
    box.appendChild(div);
    try {
      box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) {
      /* ignore */
    }
  }

  function parseJsonFromText(text) {
    var raw = String(text || '')
      .replace(/^\uFEFF/, '')
      .trim();
    if (raw === '' || raw === '0' || raw === '-1') {
      return null;
    }
    var start = raw.indexOf('{');
    if (start === -1) {
      start = raw.indexOf('[');
    }
    if (start > 0) {
      raw = raw.slice(start);
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function extractErrorMessage(json, strings) {
    if (!json || typeof json !== 'object') {
      return t(strings, 'requestFailed', 'Request failed. Please try again.');
    }
    if (json.data) {
      if (typeof json.data === 'string') {
        return json.data;
      }
      if (typeof json.data.message === 'string' && json.data.message) {
        return json.data.message;
      }
      if (Array.isArray(json.data) && json.data.length && typeof json.data[0] === 'string') {
        return json.data[0];
      }
    }
    if (typeof json.message === 'string' && json.message) {
      return json.message;
    }
    return t(strings, 'requestFailed', 'Request failed. Please try again.');
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.classList || !form.classList.contains('sikshya-auth__form')) {
      return;
    }
    var host = form.closest('.sikshya-auth');
    if (!host || host.getAttribute('data-sikshya-auth-ajax') !== '1') {
      return;
    }

    var cfg = buildCfg(host);
    if (!cfg.ajaxUrl) {
      return;
    }

    var kind = host.getAttribute('data-sikshya-auth-kind') || '';
    var actionKey = kind === 'register' ? 'registerAction' : 'loginAction';
    var action = cfg[actionKey];
    if (!action) {
      return;
    }

    e.preventDefault();

    var msgBox = ensureMessagesBox(form, host);
    revealCheckoutAuthPanel(form);

    setBusy(form, true);
    clearMessages(msgBox);

    var fd = new FormData(form);
    var dsRedirect = host.getAttribute('data-sikshya-auth-redirect-to');
    if (dsRedirect && String(dsRedirect).trim() !== '') {
      fd.set('redirect_to', String(dsRedirect).trim());
    }
    fd.set('action', action);

    fetch(String(cfg.ajaxUrl), {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then(function (r) {
        return r.text();
      })
      .then(function (text) {
        setBusy(form, false);
        var json = parseJsonFromText(text);
        if (!json) {
          revealCheckoutAuthPanel(form);
          showMessage(
            msgBox,
            t(cfg.strings, 'networkError', 'Something went wrong. Please try again.'),
            'error',
            cfg.strings
          );
          return;
        }
        if (json.success && json.data && json.data.redirect) {
          showMessage(
            msgBox,
            json.data.message || t(cfg.strings, 'signedInRedirect', 'Signed in. Redirecting…'),
            'success',
            cfg.strings
          );
          window.setTimeout(function () {
            window.location.assign(String(json.data.redirect));
          }, json.data.delay_ms != null ? Number(json.data.delay_ms) : 450);
          return;
        }
        revealCheckoutAuthPanel(form);
        showMessage(msgBox, extractErrorMessage(json, cfg.strings), 'error', cfg.strings);
      })
      .catch(function () {
        setBusy(form, false);
        revealCheckoutAuthPanel(form);
        showMessage(msgBox, t(cfg.strings, 'networkError', 'Network error. Please try again.'), 'error', cfg.strings);
      });
  });
})();
