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
      // Sample-course import is its own button on the Finish step (see
      // sample-import handler below); the Finish click only marks the
      // wizard complete.
      return { step: 5 };
    }
    return { step };
  }

  function getBusyButton() {
    return current >= 5 ? btnFinish : btnNext;
  }

  function getBusyLabel() {
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
      bus.textContent = getBusyLabel();
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

  // ----- Add sample course (Finish step) ----------------------------------
  // The button is independent of Finish setup: clicking it imports the
  // bundled `default` sample pack via the dedicated REST endpoint and
  // shows inline feedback. Finish setup itself just marks the wizard
  // complete, so users can:
  //   - skip the import entirely (just click Finish setup), OR
  //   - click "Add sample course" first, watch it succeed, then Finish.
  // The PHP endpoint also writes the result to a per-user transient, so
  // the celebration screen can summarize what was created either way.
  const sampleBtn = root.querySelector('[data-setup-sample-import]');
  if (sampleBtn && cfg.sampleImportUrl) {
    const labelEl = sampleBtn.querySelector('[data-setup-sample-label]');
    const helperEl = root.querySelector('[data-setup-sample-helper]');
    const statusEl = root.querySelector('[data-setup-sample-status]');
    const COUNT_LABELS = {
      courses: 'courses',
      chapters: 'chapters',
      lessons: 'lessons',
      quizzes: 'quizzes',
      questions: 'questions',
      assignments: 'assignments',
    };

    function setSampleStatus(kind, html) {
      if (!statusEl) return;
      if (!kind) {
        statusEl.removeAttribute('data-kind');
        statusEl.innerHTML = '';
        statusEl.setAttribute('hidden', '');
        return;
      }
      statusEl.setAttribute('data-kind', kind);
      statusEl.innerHTML = html;
      statusEl.removeAttribute('hidden');
    }

    function escHtml(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function summarizeCounts(counts) {
      if (!counts || typeof counts !== 'object') return '';
      const bits = [];
      Object.keys(COUNT_LABELS).forEach((k) => {
        const n = parseInt(counts[k], 10) || 0;
        if (n > 0) bits.push(`${n} ${COUNT_LABELS[k]}`);
      });
      return bits.join(', ');
    }

    sampleBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (sampleBtn.disabled) return;

      const originalLabel = labelEl ? labelEl.textContent : '';
      const busyText = (cfg.strings && cfg.strings.sampleAdding) || 'Adding sample course…';
      sampleBtn.disabled = true;
      sampleBtn.classList.add('is-busy');
      if (labelEl) labelEl.textContent = busyText;
      if (helperEl) helperEl.setAttribute('hidden', '');
      setSampleStatus('busy', `<span class="sikshya-setup__sample-spinner" aria-hidden="true"></span>${escHtml(busyText)}`);

      try {
        const res = await fetch(cfg.sampleImportUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce,
          },
          body: '{}',
          credentials: 'same-origin',
        });
        const body = await res.json().catch(() => ({}));

        if (res.ok && body && body.success) {
          const counts = (body.data && body.data.counts) || {};
          const okText = (cfg.strings && cfg.strings.sampleAdded) || 'Sample course added.';
          const summary = summarizeCounts(counts);
          const viewLabel = (cfg.strings && cfg.strings.sampleViewCourses) || 'View courses';
          const link = cfg.coursesUrl
            ? ` <a class="sikshya-setup__inline-link" href="${escHtml(cfg.coursesUrl)}">${escHtml(viewLabel)}</a>`
            : '';
          setSampleStatus(
            'ok',
            `<strong>${escHtml(okText)}</strong>${summary ? ` ${escHtml(`Created ${summary}.`)}` : ''}${link}`
          );
          if (labelEl) labelEl.textContent = okText;
          sampleBtn.setAttribute('aria-disabled', 'true');
          // Leave button disabled — the import is one-shot per wizard run.
          return;
        }

        const failText = (cfg.strings && cfg.strings.sampleAddFailed) || 'Sample course could not be added.';
        const reason = (body && body.message) ? String(body.message) : '';
        setSampleStatus('error', `<strong>${escHtml(failText)}</strong>${reason ? ` ${escHtml(reason)}` : ''}`);
        sampleBtn.disabled = false;
        sampleBtn.classList.remove('is-busy');
        if (labelEl) labelEl.textContent = originalLabel;
        if (helperEl) helperEl.removeAttribute('hidden');
      } catch (err) {
        const failText = (cfg.strings && cfg.strings.sampleAddFailed) || 'Sample course could not be added.';
        const reason = err && err.message ? String(err.message) : 'Network error';
        setSampleStatus('error', `<strong>${escHtml(failText)}</strong> ${escHtml(reason)}`);
        sampleBtn.disabled = false;
        sampleBtn.classList.remove('is-busy');
        if (labelEl) labelEl.textContent = originalLabel;
        if (helperEl) helperEl.removeAttribute('hidden');
      }
    });
  }
})();
