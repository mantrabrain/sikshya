(() => {
  const steps = Array.from(document.querySelectorAll('[data-setup-step]'));
  const btnPrev = document.querySelector('[data-setup-prev]');
  const btnNext = document.querySelector('[data-setup-next]');
  const btnFinish = document.querySelector('[data-setup-finish]');
  const bar = document.querySelector('[data-setup-progress]');

  if (!steps.length || !btnPrev || !btnNext || !btnFinish) {
    return;
  }

  let current = 1;
  const max = steps.length;

  function setHidden(el, hidden) {
    if (hidden) {
      el.setAttribute('hidden', '');
    } else {
      el.removeAttribute('hidden');
    }
  }

  function render() {
    steps.forEach((s) => {
      const n = parseInt(s.getAttribute('data-setup-step') || '0', 10);
      setHidden(s, n !== current);
    });

    btnPrev.disabled = current <= 1;

    const isLast = current >= max;
    setHidden(btnNext, isLast);
    setHidden(btnFinish, !isLast);

    if (bar) {
      const pct = Math.round((current / max) * 100);
      bar.style.width = `${pct}%`;
      bar.setAttribute('aria-valuenow', String(pct));
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  btnPrev.addEventListener('click', () => {
    current = Math.max(1, current - 1);
    render();
  });

  btnNext.addEventListener('click', () => {
    current = Math.min(max, current + 1);
    render();
  });

  render();
})();

