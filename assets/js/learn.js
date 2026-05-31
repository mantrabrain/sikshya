/**
 * Sikshya Learn-shell client.
 *
 * Drives the lesson-page interactions that were previously inlined in
 * `templates/single-lesson.php` (an ~500-line `<script>` block). Behaviour is
 * identical; the per-page config (REST base URL, nonce, course/lesson ids,
 * translatable strings) is delivered through a `<script id="sikshya-learn-config" type="application/json">`
 * tag that the template renders just above this file's `<script src>` tag.
 *
 * Surfaces handled:
 *   1. Sidebar outline toggle (with Escape-to-close + lg-viewport collapse).
 *   2. Content tab switcher.
 *   3. Top-bar IntersectionObserver (drives a CSS custom-prop for sidebar gap).
 *   4. Auto-focus current chapter + item on load.
 *   5. Progress popover (click-outside + Escape).
 *   6. "Mark complete" button → POST /sikshya/v1/me/lesson-complete.
 *   7. Notes panel CRUD → /me/content-note.
 *   8. Assignment dropzone + multipart submit → /me/assignment-submit.
 *
 * Single-IIFE module — no globals beyond the config it reads.
 */
(() => {
  // ---- Config ----------------------------------------------------------

  /** @type {{rest:{url:string,nonce:string}, course_id:number, lesson_id:number, i18n:Record<string,string>}|null} */
  let cfg = null;
  try {
    const el = document.getElementById('sikshya-learn-config');
    cfg = el && el.textContent ? JSON.parse(el.textContent) : null;
  } catch (_) {
    cfg = null;
  }
  // Hard-fail safe: if config is missing, the interactive surfaces below
  // silently skip. The page remains readable.
  const REST_BASE = (cfg && cfg.rest && cfg.rest.url) ? String(cfg.rest.url) : '';
  const REST_NONCE = (cfg && cfg.rest && cfg.rest.nonce) ? String(cfg.rest.nonce) : '';
  const COURSE_ID = (cfg && Number(cfg.course_id)) || 0;
  const LESSON_ID = (cfg && Number(cfg.lesson_id)) || 0;
  /** @type {Record<string,string>} */
  const I18N = (cfg && cfg.i18n && typeof cfg.i18n === 'object') ? cfg.i18n : {};
  const t = (key, fallback) => (typeof I18N[key] === 'string' && I18N[key] !== '') ? I18N[key] : (fallback || '');

  // ---- 1. Sidebar outline toggle --------------------------------------

  const root = document.documentElement;
  const overlay = document.querySelector('[data-sikshya-outline-overlay]');
  const toggleBtn = document.querySelector('[data-sikshya-toggle-outline]');
  const sidebarEl = document.querySelector('[data-sikshya-outline]');
  const openClass = 'sikshya-outlineOpen';
  const collapsedClass = 'sikshya-sidebarCollapsed';

  // Restore-focus target after the drawer closes — improves keyboard UX.
  let drawerLastFocus = null;

  function setOpen(isOpen) {
    root.classList.toggle(openClass, isOpen);
    if (overlay) overlay.hidden = !isOpen;
    // Announce the drawer as a modal dialog to assistive tech when open
    // (mobile-only — desktop sidebar is in-flow, not a dialog).
    if (sidebarEl) {
      if (isOpen) {
        sidebarEl.setAttribute('aria-modal', 'true');
        sidebarEl.setAttribute('role', 'dialog');
      } else {
        sidebarEl.removeAttribute('aria-modal');
        sidebarEl.removeAttribute('role');
      }
    }
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) {
      drawerLastFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    } else if (drawerLastFocus && document.contains(drawerLastFocus)) {
      drawerLastFocus.focus();
      drawerLastFocus = null;
    }
  }

  toggleBtn?.addEventListener('click', () => {
    if (window.matchMedia && window.matchMedia('(min-width: 1024px)').matches) {
      root.classList.toggle(collapsedClass);
      return;
    }
    setOpen(!root.classList.contains(openClass));
  });
  overlay?.addEventListener('click', () => setOpen(false));
  window.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    setOpen(false);
    root.classList.remove(collapsedClass);
  });

  // Lightweight focus trap for the mobile drawer. When the drawer is open
  // (role=dialog, aria-modal=true), Tab cycles within the drawer instead
  // of leaking out to behind-the-overlay content. Desktop is unaffected
  // because the drawer is in-flow there.
  window.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    if (!sidebarEl || !root.classList.contains(openClass)) return;
    const focusables = sidebarEl.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;
    if (e.shiftKey && active === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && active === last) {
      e.preventDefault();
      first.focus();
    } else if (!sidebarEl.contains(active instanceof Node ? active : null)) {
      // Focus had escaped the drawer (eg. closed dropdown) — pull it back.
      e.preventDefault();
      first.focus();
    }
  });

  // ---- 2. Content tab switcher ----------------------------------------
  // Scope to main content strip only — Pro sidebar / other UI may reuse the same hooks.

  const mainTabsSection = document.querySelector('.sikshya-learnContent > .sikshya-tabsSection');
  const tabs = mainTabsSection ? mainTabsSection.querySelectorAll('[data-sikshya-tab]') : [];
  const panels = mainTabsSection ? mainTabsSection.querySelectorAll('[data-sikshya-panel]') : [];

  // Find the scroll container that actually owns the page scroll:
  //   - Desktop: `.sikshya-learnContent` has its own internal scrollport
  //     (overflow-y: auto kicks in at 1024px+).
  //   - Mobile: the document itself scrolls (overflow on learnContent is
  //     visible, html scrolls).
  // Returns the element whose `scrollTop` actually changes when the user
  // scrolls. Falls back to `document.scrollingElement` if no ancestor of
  // the tabs bar is scrollable.
  function findScrollContainer(el) {
    let parent = el && el.parentElement;
    while (parent && parent !== document.body) {
      const ov = window.getComputedStyle(parent).overflowY;
      if (ov === 'auto' || ov === 'scroll') return parent;
      parent = parent.parentElement;
    }
    return document.scrollingElement || document.documentElement;
  }

  // After activating a tab, scroll only if the tabs bar isn't already
  // visible in the scrollport. Never scrolls UP — that feels like the page
  // jumping back to top. Works on BOTH desktop (where .learnContent owns
  // the scrollport) and mobile (where the document scrolls).
  function scrollTabsIfNeeded() {
    if (!mainTabsSection) return;
    const tabsBar = mainTabsSection.querySelector('.sikshya-tabsBar');
    if (!tabsBar) return;
    const scrollEl = findScrollContainer(tabsBar);
    const isDoc = scrollEl === document.scrollingElement || scrollEl === document.documentElement || scrollEl === document.body;
    const tabsRect = tabsBar.getBoundingClientRect();
    // Determine the visible scrollport rect.
    let portTop = 0;
    let portBottom = window.innerHeight || document.documentElement.clientHeight;
    let currentScroll = isDoc ? (window.scrollY || document.documentElement.scrollTop || 0) : scrollEl.scrollTop;
    if (!isDoc) {
      const portRect = scrollEl.getBoundingClientRect();
      portTop = portRect.top;
      portBottom = portRect.top + portRect.height;
    }
    // Tabs bar is fully visible inside the scrollport (or scrolled past
    // the top into the body — user is reading content) → leave alone.
    if (tabsRect.top <= portBottom) return;
    // Otherwise scroll DOWN so the tabs bar lands just below the sticky
    // chrome (topbar + lesson header).
    const topbarEl = document.querySelector('.sikshya-learnTopbar');
    const headerEl = document.querySelector('.sikshya-contentPanel--header');
    const topbarH = topbarEl && !root.classList.contains('sikshya-focusMode') ? topbarEl.offsetHeight : 0;
    const headerH = headerEl ? headerEl.offsetHeight : 0;
    const stickyOffset = topbarH + headerH + 12;
    const delta = tabsRect.top - (portTop + stickyOffset);
    const newScroll = Math.max(0, currentScroll + delta);
    if (scrollEl.scrollTo) {
      scrollEl.scrollTo({ top: newScroll, behavior: 'smooth' });
    } else {
      scrollEl.scrollTop = newScroll;
    }
  }

  tabs.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-sikshya-tab');
      tabs.forEach((b) => {
        const isActive = b === btn;
        b.classList.toggle('is-active', isActive);
        // a11y: announce the active tab to assistive tech + manage tab roving.
        if (b.getAttribute('role') === 'tab') {
          b.setAttribute('aria-selected', isActive ? 'true' : 'false');
          b.setAttribute('tabindex', isActive ? '0' : '-1');
        }
      });
      panels.forEach((p) => {
        const isActive = p.getAttribute('data-sikshya-panel') === target;
        p.classList.toggle('is-active', isActive);
        // a11y: hide inactive panels from screen readers + keyboard nav.
        if (p.getAttribute('role') === 'tabpanel') {
          if (isActive) {
            p.removeAttribute('hidden');
          } else {
            p.setAttribute('hidden', '');
          }
        }
      });
      scrollTabsIfNeeded();
    });
  });

  // ---- 3. Top-bar IntersectionObserver --------------------------------
  // When the top bar scrolls away, remove the sidebar's reserved gap.

  const topbar = document.querySelector('.sikshya-learnTopbar');
  if (topbar && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      const e = entries[0];
      root.style.setProperty('--sikshya-learn-topbar-visible', e && e.isIntersecting ? '1' : '0');
    }, { threshold: [0] });
    io.observe(topbar);
  }

  // ---- 4. Auto-focus current chapter on load --------------------------

  window.addEventListener('load', () => {
    const scrollWrap = document.querySelector('[data-sikshya-outline] .sikshya-learnSidebar__scroll');
    if (!scrollWrap) return;

    const currentChapterSummary = scrollWrap.querySelector('[data-sikshya-current-chapter="1"] > summary');
    const currentLink = scrollWrap.querySelector('li[data-sikshya-current="1"] a');

    // Ensure the chapter header is visible, then the current item (centered).
    currentChapterSummary?.scrollIntoView({ block: 'nearest' });
    currentLink?.scrollIntoView({ block: 'center' });
  }, { once: true });

  // ---- 5. Progress popover --------------------------------------------

  const progressBtn = document.querySelector('[data-sikshya-progress-btn]');
  const popover = document.querySelector('[data-sikshya-progress-popover]');
  function closeProgress() {
    if (!progressBtn || !popover) return;
    popover.hidden = true;
    progressBtn.setAttribute('aria-expanded', 'false');
  }
  if (progressBtn && popover) {
    progressBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      popover.hidden = !popover.hidden;
      progressBtn.setAttribute('aria-expanded', popover.hidden ? 'false' : 'true');
    });
    document.addEventListener('click', (e) => {
      if (popover.hidden) return;
      const tgt = e.target;
      if (!(tgt instanceof Node)) return;
      if (popover.contains(tgt) || progressBtn.contains(tgt)) return;
      closeProgress();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      closeProgress();
    });
  }

  // ---- 6. Mark lesson complete ---------------------------------------

  const completeBtn = document.querySelector('[data-sikshya-mark-complete]');
  if (completeBtn) {
    completeBtn.addEventListener('click', async () => {
      const courseId = completeBtn.getAttribute('data-course-id') || '';
      const lessonId = completeBtn.getAttribute('data-lesson-id') || '';
      if (!REST_BASE || !REST_NONCE || !courseId || !lessonId) return;

      const prevText = completeBtn.textContent || '';
      completeBtn.setAttribute('disabled', 'disabled');
      completeBtn.textContent = t('saving', 'Saving…');
      try {
        const res = await fetch(REST_BASE.replace(/\/?$/, '/') + 'me/lesson-complete', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': REST_NONCE,
          },
          body: JSON.stringify({ course_id: Number(courseId), lesson_id: Number(lessonId) }),
          credentials: 'same-origin',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && (json.message || (json.data && json.data.message))) || t('failed_complete', 'Could not mark complete. Try again.'));
        }
        window.location.reload();
      } catch (err) {
        completeBtn.removeAttribute('disabled');
        completeBtn.textContent = prevText;
        // eslint-disable-next-line no-console
        console.error(err);
      }
    });
  }

  // ---- 7. Notes panel CRUD --------------------------------------------

  const notesShell = document.querySelector('[data-sikshya-notes-shell]');
  const notesList = document.querySelector('[data-sikshya-notes-list]');
  const notesEmpty = document.querySelector('[data-sikshya-notes-empty]');
  const noteNewTa = document.querySelector('[data-sikshya-note-new]');
  const noteAdd = document.querySelector('[data-sikshya-note-add]');
  const noteStatus = document.querySelector('[data-sikshya-note-status]');
  if (notesShell && notesList && noteNewTa && noteAdd) {
    function setStatus(txt) {
      if (noteStatus) noteStatus.textContent = txt || '';
    }

    function noteUrl(extra) {
      const u = new URL(REST_BASE.replace(/\/?$/, '/') + 'me/content-note', window.location.href);
      u.searchParams.set('course_id', String(COURSE_ID));
      u.searchParams.set('content_id', String(LESSON_ID));
      if (extra && typeof extra.note_id === 'string') {
        u.searchParams.set('note_id', extra.note_id);
      }
      return u.toString();
    }

    function formatWhen(iso) {
      try {
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '' : d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
      } catch (_) {
        return '';
      }
    }

    function renderNotes(items) {
      notesList.innerHTML = '';
      const arr = Array.isArray(items) ? items.slice() : [];
      if (notesEmpty) {
        notesEmpty.hidden = arr.length > 0;
      }
      arr.forEach((n) => {
        const id = n && typeof n.id === 'string' ? n.id : '';
        const text = n && typeof n.text === 'string' ? n.text : '';
        const when = formatWhen(n.created_at || '');
        if (!id || !text) return;
        const li = document.createElement('li');
        li.className = 'sikshya-learnNotes__item';
        li.dataset.noteId = id;
        const card = document.createElement('div');
        card.className = 'sikshya-learnNotes__card';
        const meta = document.createElement('div');
        meta.className = 'sikshya-learnNotes__meta';
        const timeEl = document.createElement('time');
        timeEl.className = 'sikshya-learnNotes__time';
        timeEl.dateTime = n.created_at || '';
        timeEl.textContent = when || '';
        meta.appendChild(timeEl);
        const body = document.createElement('div');
        body.className = 'sikshya-learnNotes__body';
        body.textContent = text;
        const ta = document.createElement('textarea');
        ta.className = 'sikshya-learnNotes__edit sikshya-quizQ__textarea';
        ta.hidden = true;
        ta.rows = 4;
        ta.value = text;
        ta.setAttribute('aria-label', t('note_text_aria', 'Note text'));
        const actions = document.createElement('div');
        actions.className = 'sikshya-learnNotes__actions';
        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'sikshya-btn sikshya-btn--outline sikshya-learnNotes__btn';
        editBtn.textContent = t('edit', 'Edit');
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.hidden = true;
        saveBtn.className = 'sikshya-btn sikshya-btn--outline sikshya-learnNotes__btn';
        saveBtn.textContent = t('save', 'Save');
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.hidden = true;
        // Same outline base as Edit/Save — only the tone modifier differs.
        cancelBtn.className = 'sikshya-btn sikshya-btn--outline sikshya-learnNotes__btn sikshya-learnNotes__btn--muted';
        cancelBtn.textContent = t('cancel', 'Cancel');
        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        // Same outline base as Edit/Save — danger modifier paints the border + text red.
        delBtn.className = 'sikshya-btn sikshya-btn--outline sikshya-learnNotes__btn sikshya-learnNotes__btn--danger';
        delBtn.textContent = t('delete', 'Delete');
        actions.append(editBtn, saveBtn, cancelBtn, delBtn);
        card.append(meta, body, ta, actions);
        li.appendChild(card);
        notesList.appendChild(li);

        function setEditing(on) {
          body.hidden = on;
          ta.hidden = !on;
          editBtn.hidden = on;
          saveBtn.hidden = !on;
          cancelBtn.hidden = !on;
          if (!on) {
            ta.value = body.textContent || '';
          } else {
            ta.focus();
          }
        }
        editBtn.addEventListener('click', () => setEditing(true));
        cancelBtn.addEventListener('click', () => setEditing(false));
        saveBtn.addEventListener('click', async () => {
          saveBtn.disabled = true;
          setStatus(t('saving', 'Saving…'));
          try {
            const res = await fetch(REST_BASE.replace(/\/?$/, '/') + 'me/content-note', {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': REST_NONCE },
              credentials: 'same-origin',
              body: JSON.stringify({
                course_id: Number(COURSE_ID),
                content_id: Number(LESSON_ID),
                note_id: id,
                text: ta.value,
              }),
            });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json || json.ok !== true) {
              throw new Error((json && json.message) || 'fail');
            }
            body.textContent = ta.value;
            setEditing(false);
            setStatus(t('saved', 'Saved.'));
            window.setTimeout(() => setStatus(''), 1600);
          } catch (_) {
            setStatus(t('failed', 'Could not save. Try again.'));
          } finally {
            saveBtn.disabled = false;
          }
        });
        delBtn.addEventListener('click', async () => {
          if (!window.confirm(t('confirm_delete_note', 'Delete this note?'))) return;
          delBtn.disabled = true;
          setStatus(t('saving', 'Saving…'));
          try {
            const res = await fetch(noteUrl({ note_id: id }), {
              method: 'DELETE',
              headers: { 'X-WP-Nonce': REST_NONCE },
              credentials: 'same-origin',
            });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json || json.ok !== true) {
              throw new Error((json && json.message) || 'fail');
            }
            li.remove();
            const left = notesList.querySelectorAll('.sikshya-learnNotes__item').length;
            if (notesEmpty) notesEmpty.hidden = left > 0;
            setStatus(t('removed', 'Note removed.'));
            window.setTimeout(() => setStatus(''), 1600);
          } catch (_) {
            setStatus(t('failed', 'Could not save. Try again.'));
          } finally {
            delBtn.disabled = false;
          }
        });
      });
    }

    async function loadNotes() {
      if (!REST_BASE || !REST_NONCE) return;
      try {
        const url = noteUrl();
        const res = await fetch(url, { method: 'GET', headers: { 'X-WP-Nonce': REST_NONCE }, credentials: 'same-origin' });
        const json = await res.json().catch(() => null);
        if (res.ok && json && json.ok && json.data) {
          const items = Array.isArray(json.data.notes) ? json.data.notes : [];
          renderNotes(items.filter((it) => it && it.id && typeof it.text === 'string'));
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
      }
    }

    document.addEventListener('click', (e) => {
      const tgt = e.target;
      if (!(tgt instanceof Element)) return;
      if (tgt.matches('[data-sikshya-tab="notes"]')) void loadNotes();
    });

    noteAdd.addEventListener('click', async () => {
      const text = String(noteNewTa.value || '').trim();
      if (!text || !REST_BASE || !REST_NONCE) return;
      noteAdd.disabled = true;
      setStatus(t('saving', 'Saving…'));
      try {
        const res = await fetch(REST_BASE.replace(/\/?$/, '/') + 'me/content-note', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': REST_NONCE },
          credentials: 'same-origin',
          body: JSON.stringify({
            course_id: Number(COURSE_ID),
            content_id: Number(LESSON_ID),
            text,
          }),
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && json.message) || 'fail');
        }
        noteNewTa.value = '';
        await loadNotes();
        setStatus(t('added', 'Note added.'));
        window.setTimeout(() => setStatus(''), 1600);
      } catch (_) {
        setStatus(t('failed', 'Could not save. Try again.'));
      } finally {
        noteAdd.disabled = false;
      }
    });
  }

  // ---- 8. Assignment dropzone + multipart submit ---------------------

  const asgRemoveLbl = t('asg_remove', 'Remove');
  const asgTooManyMsg = t('asg_too_many', 'Too many files for this assignment.');

  function initAssignmentDropzones() {
    document.querySelectorAll('[data-sikshya-dropzone]').forEach((dz) => {
      const input = dz.querySelector('input[type="file"]');
      const list = dz.querySelector('[data-sikshya-dropzone-list]');
      if (!input || !list) return;
      const maxAttr = dz.getAttribute('data-sikshya-max-files');
      const maxN = maxAttr ? parseInt(maxAttr, 10) : 0;
      const form = dz.closest('form');
      const statusEl = form ? form.querySelector('[data-sikshya-assignment-status]') : null;

      function capAndApply(filesArr) {
        let next = filesArr.slice();
        if (maxN > 0 && next.length > maxN) {
          if (statusEl) statusEl.textContent = asgTooManyMsg;
          next = next.slice(0, maxN);
        }
        const dt = new DataTransfer();
        next.forEach((f) => {
          try {
            dt.items.add(f);
          } catch (_) {
            /* ignore invalid file entries */
          }
        });
        input.files = dt.files;
        renderList();
        if (statusEl && statusEl.textContent === asgTooManyMsg) {
          window.setTimeout(() => {
            if (statusEl.textContent === asgTooManyMsg) statusEl.textContent = '';
          }, 4000);
        }
      }

      function renderList() {
        const files = input.files;
        list.innerHTML = '';
        if (!files || !files.length) {
          list.hidden = true;
          return;
        }
        list.hidden = false;
        for (let i = 0; i < files.length; i++) {
          const li = document.createElement('li');
          const name = document.createElement('span');
          name.textContent = files[i].name;
          const rm = document.createElement('button');
          rm.type = 'button';
          rm.className = 'sikshya-assignmentDropzone__remove';
          rm.textContent = asgRemoveLbl;
          rm.setAttribute('data-remove-index', String(i));
          li.appendChild(name);
          li.appendChild(rm);
          list.appendChild(li);
        }
      }

      list.addEventListener('click', (e) => {
        const btn = e.target && e.target.closest ? e.target.closest('[data-remove-index]') : null;
        if (!btn) return;
        const idx = parseInt(btn.getAttribute('data-remove-index') || '0', 10);
        const cur = Array.from(input.files || []);
        if (idx < 0 || idx >= cur.length) return;
        cur.splice(idx, 1);
        capAndApply(cur);
      });

      ['dragenter', 'dragover'].forEach((ev) => {
        dz.addEventListener(ev, (e) => {
          e.preventDefault();
          e.stopPropagation();
          if (ev === 'dragover' && e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
          dz.classList.add('is-dragover');
        });
      });
      ['dragleave', 'dragend'].forEach((ev) => {
        dz.addEventListener(ev, (e) => {
          e.preventDefault();
          dz.classList.remove('is-dragover');
        });
      });
      dz.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.remove('is-dragover');
        const dropped = Array.from((e.dataTransfer && e.dataTransfer.files) || []);
        if (!dropped.length) return;
        const existing = Array.from(input.files || []);
        capAndApply(existing.concat(dropped));
      });

      input.addEventListener('change', () => {
        capAndApply(Array.from(input.files || []));
      });
    });
  }

  const bootEl = document.getElementById('sikshya-assignment-boot');
  const asgForm = document.querySelector('[data-sikshya-assignment-form]');
  if (bootEl && asgForm && bootEl.textContent) {
    let boot = null;
    try {
      boot = JSON.parse(bootEl.textContent);
    } catch (_) {
      boot = null;
    }
    if (boot && boot.rest && boot.nonce) {
      initAssignmentDropzones();
      const statusEl = asgForm.querySelector('[data-sikshya-assignment-status]');
      asgForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (statusEl) statusEl.textContent = '';
        const submitBtn = asgForm.querySelector('[data-sikshya-assignment-submit]');
        const prev = submitBtn ? submitBtn.textContent || '' : '';
        if (submitBtn) submitBtn.disabled = true;
        try {
          const fd = new FormData(asgForm);
          const res = await fetch(boot.rest.replace(/\/?$/, '/') + 'me/assignment-submit', {
            method: 'POST',
            headers: { 'X-WP-Nonce': boot.nonce },
            credentials: 'same-origin',
            body: fd,
          });
          const json = await res.json().catch(() => null);
          if (!res.ok || !json || json.ok !== true) {
            const msg = (json && (json.message || (json.data && json.data.message))) || t('asg_failed', 'Could not submit. Try again.');
            throw new Error(msg);
          }
          window.location.reload();
        } catch (err) {
          if (statusEl) statusEl.textContent = (err && err.message) ? err.message : t('asg_failed_short', 'Could not submit.');
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = prev;
          }
        }
      });
    }
  }

  // ---- 9. Scroll-aware topbar -----------------------------------------
  // Slide topbar out on scroll-down past 200px; back in on scroll-up.
  // rAF-throttled + passive listener -> no jank during video playback.
  // Suppressed entirely while in focus mode (handled by CSS :not()).

  const SCROLL_THRESHOLD = 200;
  let lastScrollY = 0;
  let scrollTicking = false;
  function applyChromeHidden() {
    const y = window.scrollY || document.documentElement.scrollTop || 0;
    const goingDown = y > lastScrollY;
    if (y < SCROLL_THRESHOLD) {
      root.classList.remove('sikshya-chromeHidden');
    } else if (goingDown && Math.abs(y - lastScrollY) > 6) {
      root.classList.add('sikshya-chromeHidden');
    } else if (!goingDown && Math.abs(y - lastScrollY) > 6) {
      root.classList.remove('sikshya-chromeHidden');
    }
    lastScrollY = y;
    scrollTicking = false;
  }
  window.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(applyChromeHidden);
  }, { passive: true });

  // ---- 10. More drawer toggle -----------------------------------------

  const moreToggleBtn = document.querySelector('[data-sikshya-more-toggle]');
  const moreCloseBtn = document.querySelector('[data-sikshya-more-close]');
  const moreOverlay = document.querySelector('[data-sikshya-more-overlay]');
  const moreDrawer = document.querySelector('[data-sikshya-more-drawer]');
  const moreOpenClass = 'sikshya-moreOpen';
  let moreLastFocus = null;
  function setMoreOpen(isOpen) {
    if (!moreDrawer || !moreOverlay) return;
    root.classList.toggle(moreOpenClass, isOpen);
    if (isOpen) {
      moreLastFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      moreDrawer.hidden = false;
      moreOverlay.hidden = false;
      window.setTimeout(() => moreCloseBtn?.focus(), 60);
    } else {
      window.setTimeout(() => {
        if (!root.classList.contains(moreOpenClass)) {
          moreDrawer.hidden = true;
          moreOverlay.hidden = true;
        }
      }, 230);
      if (moreLastFocus && document.contains(moreLastFocus)) {
        moreLastFocus.focus();
      }
    }
    if (moreToggleBtn) moreToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }
  moreToggleBtn?.addEventListener('click', () => setMoreOpen(!root.classList.contains(moreOpenClass)));
  moreCloseBtn?.addEventListener('click', () => setMoreOpen(false));
  moreOverlay?.addEventListener('click', () => setMoreOpen(false));

  // ---- 11. Focus mode toggle + persistence ----------------------------

  const FOCUS_KEY = 'sikshya:learn:focus';
  const focusBtn = document.querySelector('[data-sikshya-focus-toggle]');
  function setFocusMode(isOn, persist) {
    root.classList.toggle('sikshya-focusMode', !!isOn);
    if (focusBtn) focusBtn.setAttribute('aria-pressed', isOn ? 'true' : 'false');
    if (persist) {
      try {
        if (isOn) {
          window.localStorage.setItem(FOCUS_KEY, '1');
        } else {
          window.localStorage.removeItem(FOCUS_KEY);
        }
      } catch (_) { /* private browsing — ignore */ }
    }
  }
  // Restore from localStorage on load.
  try {
    if (window.localStorage.getItem(FOCUS_KEY) === '1') {
      setFocusMode(true, false);
    }
  } catch (_) { /* ignore */ }
  focusBtn?.addEventListener('click', () => {
    setFocusMode(!root.classList.contains('sikshya-focusMode'), true);
  });

  // ---- 12. Keyboard shortcuts -----------------------------------------
  // F focus · M sidebar · O more · N/Right next · P/Left prev · C complete · ? help
  // Guarded so typing in textareas / inputs / contenteditable never triggers.

  function isFormFocus() {
    const el = document.activeElement;
    if (!el) return false;
    if (el === document.body) return false;
    const tag = (el.tagName || '').toLowerCase();
    if (tag === 'textarea' || tag === 'input' || tag === 'select') return true;
    if (el.isContentEditable) return true;
    return false;
  }
  function clickIfExists(sel) {
    const el = document.querySelector(sel);
    if (el && !el.hasAttribute('disabled')) el.click();
  }
  function navigateToDock(direction) {
    const link = document.querySelector(
      direction === 'next'
        ? 'a.sikshya-learnDock__btn--next'
        : 'a.sikshya-learnDock__btn--prev'
    );
    if (link && link.getAttribute('href')) window.location.assign(link.getAttribute('href'));
  }
  window.addEventListener('keydown', (e) => {
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    if (isFormFocus()) return;
    const k = (e.key || '').toLowerCase();
    if (k === 'escape') {
      // Esc exits focus mode if active, OR closes drawer, OR closes outline overlay.
      if (root.classList.contains('sikshya-focusMode')) {
        setFocusMode(false, true);
        return;
      }
      if (root.classList.contains(moreOpenClass)) {
        setMoreOpen(false);
        return;
      }
      return;
    }
    if (k === 'f') { setFocusMode(!root.classList.contains('sikshya-focusMode'), true); e.preventDefault(); return; }
    if (k === 'm') { toggleBtn?.click(); e.preventDefault(); return; }
    if (k === 'o') { setMoreOpen(!root.classList.contains(moreOpenClass)); e.preventDefault(); return; }
    if (k === 'n' || k === 'arrowright') { navigateToDock('next'); e.preventDefault(); return; }
    if (k === 'p' || k === 'arrowleft') { navigateToDock('prev'); e.preventDefault(); return; }
    if (k === 'c') { clickIfExists('[data-sikshya-mark-complete]:not([disabled])'); e.preventDefault(); return; }
    if (k === '?') { toggleShortcutHelp(); e.preventDefault(); return; }
  });

  // ---- 13. Mark-Complete pill — reveal on threshold ------------------
  // Fades in once the learner has either watched >=80% of a <video> in
  // the lesson, OR scrolled past ~70% of the lesson body bottom.

  const completePill = document.querySelector('[data-sikshya-complete-pill]');
  if (completePill) {
    let pillRevealed = false;
    function revealPill() {
      if (pillRevealed) return;
      pillRevealed = true;
      completePill.hidden = false;
      // next frame so the transition fires.
      window.requestAnimationFrame(() => completePill.classList.add('is-visible'));
    }
    // Video threshold.
    document.querySelectorAll('.sikshya-lesson-embed--video video').forEach((v) => {
      v.addEventListener('timeupdate', () => {
        if (!v.duration || !isFinite(v.duration)) return;
        if (v.currentTime / v.duration >= 0.8) revealPill();
      });
      v.addEventListener('ended', revealPill);
    });
    // Scroll threshold (text/audio lessons or videos without metadata).
    const overviewWell = document.querySelector('[data-sikshya-overview-well]');
    if (overviewWell) {
      window.addEventListener('scroll', () => {
        if (pillRevealed) return;
        const rect = overviewWell.getBoundingClientRect();
        const viewport = window.innerHeight || document.documentElement.clientHeight;
        // 70% past the bottom of the well.
        if (rect.bottom < viewport * 0.7) revealPill();
      }, { passive: true });
    }
  }

  // ---- 13b. Media embed fallback ------------------------------------
  // Show the hidden `.sikshya-lesson-embed__fallback` element when the
  // in-page player can't load the source (broken file, blocked iframe,
  // mixed-content). Renders an alternative "open in a new tab" link so
  // learners aren't stuck with a blank box.
  (function wireMediaFallbacks() {
    const reveal = (wrap) => {
      if (!wrap) return;
      const fb = wrap.querySelector(':scope > .sikshya-lesson-embed__fallback');
      if (fb && fb.hasAttribute('hidden')) fb.removeAttribute('hidden');
    };
    document.querySelectorAll('.sikshya-lesson-embed').forEach((wrap) => {
      // <video> / <audio>: native `error` event on the element or its
      // child <source>. Capture phase because `error` doesn't bubble.
      wrap.querySelectorAll('video, audio').forEach((m) => {
        m.addEventListener('error', () => reveal(wrap));
        m.querySelectorAll('source').forEach((s) => {
          s.addEventListener('error', () => reveal(wrap));
        });
      });
      // <iframe>: cross-origin failures are silent, but same-origin or
      // network-level errors still fire `error`. We also start a soft
      // 12s "still nothing?" timer to nudge the fallback when the iframe
      // never loads at all (eg. provider blocking) — cleared on `load`.
      wrap.querySelectorAll('iframe').forEach((f) => {
        let settled = false;
        const done = () => { settled = true; };
        f.addEventListener('load', done, { once: true });
        f.addEventListener('error', () => reveal(wrap));
        window.setTimeout(() => { if (!settled) reveal(wrap); }, 12000);
      });
    });
  })();

  // ---- 14. Shortcut help popover -------------------------------------
  // Lightweight; rendered on demand. Esc to close.
  let helpEl = null;
  function toggleShortcutHelp() {
    if (helpEl && helpEl.parentNode) {
      helpEl.parentNode.removeChild(helpEl);
      helpEl = null;
      return;
    }
    const wrap = document.createElement('div');
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'false');
    wrap.setAttribute('aria-label', t('shortcuts_title', 'Keyboard shortcuts'));
    wrap.style.cssText = [
      'position:fixed',
      'right:18px',
      'bottom:calc(var(--sikshya-learn-content-nav-h) + 80px)',
      'z-index:120',
      'background:var(--sikshya-learn-surface)',
      'color:var(--sikshya-learn-text)',
      'border:1px solid var(--sikshya-learn-border)',
      'border-radius:12px',
      'box-shadow:var(--sikshya-learn-shadow-md)',
      'padding:14px 16px',
      'font-size:13px',
      'line-height:1.5',
      'max-width:280px',
    ].join(';');
    const rows = [
      ['F', t('sc_focus', 'Toggle focus mode')],
      ['M', t('sc_menu', 'Toggle sidebar')],
      ['O', t('sc_open', 'Open More')],
      ['N · →', t('sc_next', 'Next lesson')],
      ['P · ←', t('sc_prev', 'Previous lesson')],
      ['C', t('sc_complete', 'Mark complete')],
      ['Esc', t('sc_esc', 'Close / exit')],
    ];
    wrap.innerHTML = '<strong style="display:block;margin-bottom:8px;">' + t('shortcuts_title', 'Keyboard shortcuts') + '</strong>'
      + rows.map(([k, v]) => '<div style="display:flex;justify-content:space-between;gap:12px;padding:2px 0;"><kbd style="font-family:inherit;background:var(--sikshya-learn-accent-wash);border-radius:6px;padding:1px 8px;font-size:12px;">' + k + '</kbd><span style="color:var(--sikshya-learn-muted-strong)">' + v + '</span></div>').join('');
    document.body.appendChild(wrap);
    helpEl = wrap;
    // Close on outside click.
    window.setTimeout(() => {
      document.addEventListener('click', function onOutside(e) {
        if (!helpEl) { document.removeEventListener('click', onOutside); return; }
        if (e.target instanceof Node && helpEl.contains(e.target)) return;
        document.removeEventListener('click', onOutside);
        if (helpEl && helpEl.parentNode) helpEl.parentNode.removeChild(helpEl);
        helpEl = null;
      });
    }, 0);
  }
})();
