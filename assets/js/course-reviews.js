/**
 * Sikshya — Course reviews widget.
 *
 * Progressive-enhancement layer for the server-rendered reviews section in
 * templates/partials/single-course-reviews.php.  Handles star input, submit /
 * edit / delete, load-more pagination and optimistic UI updates.  Reads config
 * from data attributes on the <section id="sikshya-reviews"> container so it
 * works on any theme without relying on globals beyond `sikshyaReviewsL10n`.
 */
(function () {
    'use strict';

    var root = document.getElementById('sikshya-reviews');
    if (!root) {
        return;
    }

    var L10N = (typeof window.sikshyaReviewsL10n === 'object' && window.sikshyaReviewsL10n) || {};
    var courseId = parseInt(root.getAttribute('data-course-id'), 10) || 0;
    var restUrl = (root.getAttribute('data-rest-url') || '').replace(/\/+$/, '') + '/';
    var nonce = root.getAttribute('data-nonce') || '';
    var ratingsEnabled = root.getAttribute('data-ratings-enabled') === '1';
    var reviewsEnabled = root.getAttribute('data-reviews-enabled') === '1';

    var formEl = root.querySelector('[data-sikshya-review-form-el]');
    var ratingInput = root.querySelector('[data-sikshya-rating-input]');
    var ratingValueInput = root.querySelector('[data-sikshya-rating-value]');
    var textArea = root.querySelector('textarea[name="review_text"]');
    var submitBtn = root.querySelector('[data-sikshya-review-submit]');
    var cancelBtn = root.querySelector('[data-sikshya-review-cancel]');
    var statusEl = root.querySelector('[data-sikshya-review-status]');
    var ownWrap = root.querySelector('[data-sikshya-own-review]');
    var editBtn = root.querySelector('[data-sikshya-review-edit]');
    var deleteBtn = root.querySelector('[data-sikshya-review-delete]');
    var listEl = root.querySelector('[data-sikshya-review-list]');
    var loadMoreBtn = root.querySelector('[data-sikshya-review-load-more]');
    var sortSelect = root.querySelector('[data-sikshya-review-sort]');

    var editingReviewId = 0;

    /* ------------------------------------------------------------------
     * Rating input
     * ------------------------------------------------------------------ */
    function setRating(value, focus) {
        if (!ratingInput || !ratingValueInput) return;
        var v = Math.max(0, Math.min(5, parseInt(value, 10) || 0));
        ratingValueInput.value = String(v);
        var stars = ratingInput.querySelectorAll('.sikshya-rating-input__star');
        stars.forEach(function (star, i) {
            var n = i + 1;
            var on = n <= v;
            star.textContent = on ? '★' : '☆';
            star.setAttribute('aria-checked', on ? 'true' : 'false');
            star.classList.toggle('is-selected', on);
        });
        if (focus && stars[v - 1]) stars[v - 1].focus();
    }

    if (ratingInput) {
        ratingInput.querySelectorAll('.sikshya-rating-input__star').forEach(function (star) {
            star.addEventListener('click', function () {
                setRating(star.getAttribute('data-value'), false);
            });
            star.addEventListener('keydown', function (e) {
                var current = parseInt(ratingValueInput && ratingValueInput.value, 10) || 0;
                if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    setRating(current + 1, true);
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    setRating(current - 1, true);
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setRating(star.getAttribute('data-value'), false);
                }
            });
        });
    }

    /* ------------------------------------------------------------------
     * Status helpers
     * ------------------------------------------------------------------ */
    function showStatus(message, kind) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.className = 'sikshya-course-reviews__status' + (kind ? ' is-' + kind : '');
    }

    function setBusy(busy) {
        if (submitBtn) {
            submitBtn.disabled = !!busy;
            submitBtn.textContent = busy
                ? (L10N.submitting || 'Submitting…')
                : submitBtn.getAttribute('data-label') || submitBtn.textContent;
        }
    }

    if (submitBtn) {
        submitBtn.setAttribute('data-label', submitBtn.textContent);
    }

    /* ------------------------------------------------------------------
     * REST
     * ------------------------------------------------------------------ */
    function apiFetch(path, options) {
        options = options || {};
        options.headers = Object.assign(
            {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
                Accept: 'application/json',
            },
            options.headers || {}
        );
        options.credentials = 'same-origin';
        return fetch(restUrl + path.replace(/^\/+/, ''), options).then(function (res) {
            return res
                .json()
                .catch(function () { return {}; })
                .then(function (body) {
                    return { ok: res.ok, status: res.status, body: body };
                });
        });
    }

    function apiPost(path, body) {
        return apiFetch(path, { method: 'POST', body: JSON.stringify(body || {}) });
    }

    /* ------------------------------------------------------------------
     * Form submit (create / update)
     * ------------------------------------------------------------------ */
    function submitForm(e) {
        if (e) e.preventDefault();
        var rating = ratingValueInput ? parseInt(ratingValueInput.value, 10) || 0 : 0;
        var text = textArea ? textArea.value.trim() : '';

        if (ratingsEnabled && rating < 1) {
            showStatus(L10N.pickRating || 'Please choose a rating.', 'error');
            return;
        }
        if (!ratingsEnabled && reviewsEnabled && text === '') {
            showStatus(L10N.pickRating || 'Please write a short review.', 'error');
            return;
        }

        setBusy(true);
        showStatus('', '');

        var req, method;
        if (editingReviewId > 0) {
            req = apiFetch('me/reviews/' + editingReviewId, {
                method: 'PUT',
                body: JSON.stringify({ rating: rating, review_text: text }),
            });
            method = 'update';
        } else {
            req = apiFetch('me/reviews', {
                method: 'POST',
                body: JSON.stringify({ course_id: courseId, rating: rating, review_text: text }),
            });
            method = 'create';
        }

        req.then(function (r) {
            setBusy(false);
            if (!r.ok || (r.body && r.body.success === false)) {
                showStatus((r.body && r.body.message) || L10N.genericError, 'error');
                return;
            }
            showStatus(r.body.message || '', 'success');
            // Simplest & most reliable UX: reload so the server re-renders the
            // own-review card and approved list in the right order.
            window.setTimeout(function () {
                window.location.reload();
            }, 600);
        }).catch(function () {
            setBusy(false);
            showStatus(L10N.genericError || 'Error', 'error');
        });
    }

    if (formEl) {
        formEl.addEventListener('submit', submitForm);
    }

    /* ------------------------------------------------------------------
     * Edit own review
     * ------------------------------------------------------------------ */
    function enterEditMode() {
        if (!ownWrap || !formEl) return;
        editingReviewId = parseInt(ownWrap.getAttribute('data-review-id'), 10) || 0;
        if (!editingReviewId) return;

        var ownStars = ownWrap.querySelectorAll('.sikshya-rating-star--full').length;
        setRating(ownStars, false);

        var bodyEl = ownWrap.querySelector('.sikshya-course-reviews__own-body');
        if (textArea) {
            textArea.value = bodyEl ? bodyEl.innerText.trim() : '';
        }

        ownWrap.classList.add('is-hidden');
        formEl.classList.remove('is-hidden');
        if (cancelBtn) cancelBtn.classList.remove('is-hidden');
        if (textArea) textArea.focus();
    }

    function exitEditMode() {
        editingReviewId = 0;
        if (ownWrap) ownWrap.classList.remove('is-hidden');
        if (formEl) formEl.classList.add('is-hidden');
        if (cancelBtn) cancelBtn.classList.add('is-hidden');
        showStatus('', '');
    }

    if (editBtn) editBtn.addEventListener('click', enterEditMode);
    if (cancelBtn) cancelBtn.addEventListener('click', exitEditMode);

    /* ------------------------------------------------------------------
     * Delete own review
     * ------------------------------------------------------------------ */
    if (deleteBtn && ownWrap) {
        deleteBtn.addEventListener('click', function () {
            var id = parseInt(ownWrap.getAttribute('data-review-id'), 10) || 0;
            if (!id) return;
            if (!window.confirm(L10N.confirmDelete || 'Delete this review?')) return;

            deleteBtn.disabled = true;
            apiFetch('me/reviews/' + id, { method: 'DELETE' }).then(function (r) {
                deleteBtn.disabled = false;
                if (!r.ok || (r.body && r.body.success === false)) {
                    showStatus((r.body && r.body.message) || L10N.genericError, 'error');
                    return;
                }
                window.location.reload();
            }).catch(function () {
                deleteBtn.disabled = false;
                showStatus(L10N.genericError || 'Error', 'error');
            });
        });
    }

    /* ------------------------------------------------------------------
     * Load more
     * ------------------------------------------------------------------ */
    function renderReviewItem(item) {
        var li = document.createElement('li');
        li.className = 'sikshya-course-reviews__item';
        li.setAttribute('data-review-id', String(item.id));

        var starsHtml = '';
        if (ratingsEnabled && item.rating > 0) {
            var s = '';
            for (var i = 1; i <= 5; i++) {
                s += i <= item.rating
                    ? '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>'
                    : '<span class="sikshya-rating-star">☆</span>';
            }
            starsHtml = '<span class="sikshya-rating-stars sikshya-course-reviews__item-rating" aria-hidden="true">' + s + '</span>';
        }

        var avatarHtml = item.author_avatar
            ? '<img src="' + escapeAttr(item.author_avatar) + '" alt="" class="sikshya-course-reviews__avatar" loading="lazy" />'
            : '';

        var bodyHtml = item.review_text
            ? '<div class="sikshya-course-reviews__item-body sikshya-prose"><p>' + escapeHtml(item.review_text).replace(/\n+/g, '</p><p>') + '</p></div>'
            : '';

        li.innerHTML =
            '<div class="sikshya-course-reviews__item-head">' +
            avatarHtml +
            '<div class="sikshya-course-reviews__item-meta">' +
            '<span class="sikshya-course-reviews__author">' + escapeHtml(item.author_name || '—') + '</span>' +
            '<span class="sikshya-course-reviews__time">' + escapeHtml(item.created_at_label || '') + '</span>' +
            '</div>' +
            starsHtml +
            '</div>' +
            bodyHtml;

        return li;
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s); }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            var next = parseInt(loadMoreBtn.getAttribute('data-next-page'), 10) || 2;
            var total = parseInt(loadMoreBtn.getAttribute('data-total-pages'), 10) || 1;
            var origLabel = loadMoreBtn.textContent;

            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = L10N.loading || 'Loading…';

            apiFetch('courses/' + courseId + '/reviews?page=' + next + '&per_page=10', {
                method: 'GET',
            })
                .then(function (r) {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = origLabel;
                    if (!r.ok || !r.body || !r.body.data) {
                        showStatus(L10N.genericError || 'Error', 'error');
                        return;
                    }
                    var items = r.body.data.items || [];
                    if (listEl && items.length) {
                        items.forEach(function (it) {
                            listEl.appendChild(renderReviewItem(it));
                        });
                    }
                    if (next + 1 > total) {
                        loadMoreBtn.style.display = 'none';
                    } else {
                        loadMoreBtn.setAttribute('data-next-page', String(next + 1));
                    }
                })
                .catch(function () {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = origLabel;
                    showStatus(L10N.genericError || 'Error', 'error');
                });
        });
    }

    /* ------------------------------------------------------------------
     * Sort (server-rendered; reload with query param)
     * ------------------------------------------------------------------ */
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            var v = sortSelect.value || 'newest';
            var url = new URL(window.location.href);
            url.searchParams.set('reviews_sort', v);
            window.location.href = url.toString();
        });
    }

    /* ------------------------------------------------------------------
     * Report review
     * ------------------------------------------------------------------ */
    root.querySelectorAll('[data-sikshya-review-report]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var li = btn.closest('[data-review-id]');
            var id = li ? parseInt(li.getAttribute('data-review-id'), 10) || 0 : 0;
            if (!id || !courseId) return;
            btn.disabled = true;
            apiPost('courses/' + courseId + '/reviews/' + id + '/report', {})
                .then(function (r) {
                    btn.disabled = false;
                    if (!r.ok || (r.body && r.body.success === false)) {
                        showStatus((r.body && r.body.message) || L10N.genericError, 'error');
                        return;
                    }
                    showStatus((r.body && r.body.message) || 'Reported.', 'success');
                })
                .catch(function () {
                    btn.disabled = false;
                    showStatus(L10N.genericError || 'Error', 'error');
                });
        });
    });
})();
