(() => {
  const root = document.querySelector('[data-sikshya-setup]');
  if (!root) return;

  const initial = root.getAttribute('data-initial-step') || '1';
  if (initial === 'done') return;

  const cfg = window.sikshyaSetupWizard;
  if (!cfg || !cfg.restUrl || !cfg.nonce) return;

  const form = root.querySelector('[data-setup-form]');
  const btnNext = root.querySelector('[data-setup-next]');
  const btnFinish = root.querySelector('[data-setup-finish]');
  const btnSkipAll = root.querySelector('[data-setup-skip-all]');
  const bar = root.querySelector('[data-setup-progress]');
  const toast = root.querySelector('[data-setup-toast]');
  const errBox = root.querySelector('[data-setup-error]');

  if (!form || !btnNext || !btnFinish) return;

  const current = Math.max(1, Math.min(5, parseInt(initial, 10) || 1));

  if (bar) {
    const pct = Math.round((current / 5) * 100);
    bar.style.width = `${pct}%`;
    bar.setAttribute('aria-valuenow', String(pct));
  }

  function setToast(msg, isError) {
    if (!toast) return;
    if (!msg) {
      toast.setAttribute('hidden', '');
      return;
    }
    toast.textContent = msg;
    toast.classList.toggle('sikshya-setup__toast--error', !!isError);
    toast.removeAttribute('hidden');
  }

  function clearServerError() {
    if (errBox) errBox.setAttribute('hidden', '');
  }

  function collectPayload(step) {
    if (step === 1) {
      const el = form.querySelector('[name="allow_usage_tracking"]');
      const checked = el && 'checked' in el ? !!el.checked : true;
      return { step: 1, allow_usage_tracking: checked ? '1' : '0' };
    }
    if (step === 2) {
      const keys = [
        'permalink_cart',
        'permalink_checkout',
        'permalink_account',
        'permalink_learn',
        'permalink_order',
      ];
      const o = { step: 2 };
      keys.forEach((k) => {
        const el = form.querySelector(`[name="${k}"]`);
        o[k] = el && 'value' in el ? el.value : '';
      });
      return o;
    }
    if (step === 3) {
      const keys = [
        'currency',
        'currency_position',
        'currency_decimal_places',
        'currency_thousand_separator',
        'currency_decimal_separator',
      ];
      const o = { step: 3 };
      keys.forEach((k) => {
        const el = form.querySelector(`[name="${k}"]`);
        o[k] = el && 'value' in el ? el.value : '';
      });
      return o;
    }
    if (step === 4) {
      const r = form.querySelector('input[name="learn_permalink_use_public_id"]:checked');
      return { step: 4, learn_permalink_use_public_id: r ? r.value : '1' };
    }
    if (step === 5) {
      const sample = form.querySelector('[name="import_sample_data"]');
      const wantsSample = sample && 'checked' in sample ? !!sample.checked : false;
      return { step: 5, import_sample_data: wantsSample ? '1' : '0' };
    }
    return { step };
  }

  function getBusyButton() {
    return current >= 5 ? btnFinish : btnNext;
  }

  function getBusyLabel(payload) {
    if (current >= 5 && payload && payload.import_sample_data === '1') {
      return (cfg.strings && cfg.strings.importing) || 'Importing sample data…';
    }
    return (cfg.strings && cfg.strings.saving) || 'Saving…';
  }

  async function saveAndGo() {
    clearServerError();
    setToast('');

    const payload = collectPayload(current);
    const bus = getBusyButton();
    const prevLabel = bus ? bus.textContent : '';
    if (bus) {
      bus.disabled = true;
      bus.textContent = getBusyLabel(payload);
    }
    if (current < 5) btnFinish.disabled = true;

    try {
      const res = await fetch(cfg.restUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce,
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      });
      const body = await res.json().catch(() => ({}));

      if (!res.ok) {
        const errs = Array.isArray(body.errors) ? body.errors : [body.message || 'Request failed'];
        setToast(errs.filter(Boolean).join(' '), true);
        return;
      }

      if (body.next_url) {
        window.location.assign(body.next_url);
      }
    } catch (e) {
      setToast(e && e.message ? String(e.message) : 'Network error', true);
    } finally {
      if (bus) {
        bus.disabled = false;
        bus.textContent = prevLabel;
      }
      if (current < 5) btnFinish.disabled = false;
    }
  }

  btnNext.addEventListener('click', (e) => {
    e.preventDefault();
    saveAndGo();
  });

  if (btnSkipAll) {
    btnSkipAll.addEventListener('click', (e) => {
      const msg =
        (cfg.strings && cfg.strings.confirmSkipAll) ||
        'Skip setup? You can re-run the wizard anytime from Sikshya → Tools.';
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    });
  }

  form.addEventListener('submit', (e) => {
    const sub = e.submitter;
    const isSave =
      sub && sub.getAttribute('name') === 'wizard_action' && sub.getAttribute('value') === 'save';
    if (!isSave) return;
    e.preventDefault();
    saveAndGo();
  });
})();
