(function () {
	'use strict';

	// Shared attempt state (used by timer + submit).
	var attemptId = 0;
	var startedAt = 0;
	var serverOffsetMs = 0;
	var duration = 0;
	var isSubmitting = false;
	var autoSubmitted = false;

	function getConfig() {
		return window.sikshyaQuizTaker || {};
	}

	/**
	 * Build a learner REST URL for both pretty permalinks (/wp-json/...) and plain
	 * (`index.php?rest_route=/...`). Naive string concat breaks GET when the second
	 * `?quiz_id=` is merged into the same string as `rest_route` — use searchParams.
	 *
	 * @param {string} restUrl Base from `rest_url( 'sikshya/v1/' )` (trailing slash optional).
	 * @param {string} relativePath e.g. `me/quiz-attempt` (no leading slash).
	 * @param {Record<string, string|number>|null|undefined} query Optional query args (e.g. quiz_id).
	 * @returns {string}
	 */
	function buildSikshyaRestEndpointUrl(restUrl, relativePath, query) {
		var path = String(relativePath || '').replace(/^\/+|\/+$/g, '');
		var base = String(restUrl || '').trim();
		var origin = '';
		try {
			origin = window.location.origin;
		} catch (e0) {
			origin = '';
		}

		function applyQuery(u, q) {
			if (!q) {
				return;
			}
			for (var key in q) {
				if (!Object.prototype.hasOwnProperty.call(q, key)) {
					continue;
				}
				var v = q[key];
				if (v === undefined || v === null) {
					continue;
				}
				u.searchParams.set(key, String(v));
			}
		}

		try {
			var u = base.indexOf('http') === 0 ? new URL(base) : new URL(base, origin || undefined);
			if (u.searchParams.has('rest_route')) {
				var rr = String(u.searchParams.get('rest_route') || '').replace(/\/+$/, '');
				u.searchParams.set('rest_route', rr + '/' + path);
				applyQuery(u, query);
				return u.toString();
			}
			u.pathname = (String(u.pathname).replace(/\/+$/, '/') + path).replace(/\/+/g, '/');
			applyQuery(u, query);
			return u.toString();
		} catch (e1) {
			var fallback = base.replace(/\/?$/, '/') + path;
			if (query) {
				var parts = [];
				for (var k in query) {
					if (!Object.prototype.hasOwnProperty.call(query, k)) {
						continue;
					}
					var v2 = query[k];
					if (v2 === undefined || v2 === null) {
						continue;
					}
					parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(v2)));
				}
				if (parts.length) {
					fallback += (fallback.indexOf('?') !== -1 ? '&' : '?') + parts.join('&');
				}
			}
			return fallback;
		}
	}

	function moveLi(ol, li, dir) {
		var items = Array.prototype.slice.call(ol.children);
		var idx = items.indexOf(li);
		if (idx < 0) {
			return;
		}
		var next = idx + dir;
		if (next < 0 || next >= items.length) {
			return;
		}
		var ref = items[next];
		if (dir < 0) {
			ol.insertBefore(li, ref);
		} else {
			ol.insertBefore(ref, li);
		}
	}

	function orderingPayload(ol) {
		var out = [];
		Array.prototype.forEach.call(ol.querySelectorAll('li[data-item-index]'), function (li) {
			out.push(parseInt(li.getAttribute('data-item-index'), 10));
		});
		return JSON.stringify(out);
	}

	function matchingPayload(container) {
		var map = [];
		Array.prototype.forEach.call(container.querySelectorAll('.sikshya-matching__row'), function (row) {
			var sel = row.querySelector('.sikshya-matching__select');
			if (!sel) {
				map.push(0);
				return;
			}
			map.push(parseInt(sel.value, 10));
		});
		return JSON.stringify({ map: map });
	}

	function multipleResponsePayload(block) {
		var vals = [];
		Array.prototype.forEach.call(block.querySelectorAll('.sikshya-q__mr:checked'), function (cb) {
			vals.push(parseInt(cb.value, 10));
		});
		vals.sort(function (a, b) {
			return a - b;
		});
		return JSON.stringify(vals);
	}

	function collectAnswers(form) {
		var answers = {};
		var blocks = form.querySelectorAll('.sikshya-q');

		Array.prototype.forEach.call(blocks, function (block) {
			var qid = block.getAttribute('data-qid');
			var qtype = block.getAttribute('data-qtype') || '';
			if (!qid) {
				return;
			}

			if (qtype === 'multiple_response') {
				answers[qid] = multipleResponsePayload(block);
				return;
			}

			if (qtype === 'ordering') {
				var ol = block.querySelector('.sikshya-ordering');
				if (ol) {
					answers[qid] = orderingPayload(ol);
				}
				return;
			}

			if (qtype === 'matching') {
				var mc = block.querySelector('.sikshya-matching');
				if (mc) {
					answers[qid] = matchingPayload(mc);
				}
				return;
			}

			var name = 'question_' + qid;
			var radio = block.querySelector('input[type="radio"][name="' + name + '"]:checked');
			if (radio) {
				answers[qid] = radio.value;
				return;
			}

			var ta = block.querySelector('textarea[name="' + name + '"]');
			if (ta) {
				answers[qid] = ta.value;
			}
		});

		return answers;
	}

	function collectQuestionOrder(form) {
		var out = [];
		var blocks = form.querySelectorAll('.sikshya-q');
		Array.prototype.forEach.call(blocks, function (b) {
			var id = b.getAttribute('data-qid');
			if (id) {
				out.push(parseInt(id, 10));
			}
		});
		return out;
	}

	function setupOnePerPage(form) {
		var cfg = getConfig();
		if (!cfg.advanced || !cfg.advanced.one_per_page) {
			return;
		}
		var blocks = form.querySelectorAll('.sikshya-q');
		if (blocks.length <= 1) {
			return;
		}
		form.classList.add('sikshya-quizForm--onePage');
		var cur = 0;
		var submit = form.querySelector('.sikshya-quiz-submit');
		var resultEl = form.querySelector('.sikshya-quiz-result');
		var actions = form.querySelector('.sikshya-quizActions');
		var nav = document.createElement('div');
		nav.className = 'sikshya-quizPager';
		nav.setAttribute('data-sikshya-quiz-pager', '');
		var prev = document.createElement('button');
		prev.type = 'button';
		prev.className = 'sikshya-btn sikshya-btn--outline sikshya-btn--sm sikshya-quizPager__prev';
		prev.textContent = (cfg && cfg.i18n && cfg.i18n.previous) ? cfg.i18n.previous : 'Previous';
		var next = document.createElement('button');
		next.type = 'button';
		next.className = 'sikshya-btn sikshya-btn--primary sikshya-btn--sm sikshya-quizPager__next';
		next.textContent = (cfg && cfg.i18n && cfg.i18n.next) ? cfg.i18n.next : 'Next';
		var label = document.createElement('p');
		label.className = 'sikshya-quizPager__label';
		nav.appendChild(prev);
		nav.appendChild(label);
		nav.appendChild(next);
		if (resultEl) {
			form.insertBefore(nav, resultEl);
		} else if (actions) {
			form.insertBefore(nav, actions);
		} else {
			form.appendChild(nav);
		}
		function show(i) {
			for (var j = 0; j < blocks.length; j++) {
				blocks[j].hidden = j !== i;
			}
			cur = i;
			prev.disabled = cur <= 0;
			next.disabled = cur >= blocks.length - 1;
			label.textContent = cur + 1 + ' / ' + blocks.length;
			if (submit) {
				submit.hidden = cur !== blocks.length - 1;
			}
		}
		show(0);
		prev.addEventListener('click', function () {
			if (cur > 0) {
				show(cur - 1);
			}
		});
		next.addEventListener('click', function () {
			if (cur < blocks.length - 1) {
				show(cur + 1);
			}
		});
	}

	function bindOrdering(form) {
		form.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || !t.classList) {
				return;
			}
			var li = t.closest ? t.closest('li') : null;
			var ol = li && li.parentElement && li.parentElement.classList.contains('sikshya-ordering') ? li.parentElement : null;
			if (!ol || !li) {
				return;
			}
			if (t.classList.contains('sikshya-ordering__up')) {
				e.preventDefault();
				moveLi(ol, li, -1);
				refocusOrderingButton(li, '.sikshya-ordering__up');
			} else if (t.classList.contains('sikshya-ordering__down')) {
				e.preventDefault();
				moveLi(ol, li, 1);
				refocusOrderingButton(li, '.sikshya-ordering__down');
			}
		});

		// Keyboard alternative for drag/reorder. Focus an item (li is made
		// focusable below via tabindex) or focus is already inside an
		// ordering button — Alt+Arrow reorders. Plain ↑/↓ still scrolls.
		form.addEventListener('keydown', function (e) {
			if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') {
				return;
			}
			if (!e.altKey) {
				return;
			}
			var t = e.target;
			var li = t && t.closest ? t.closest('.sikshya-ordering__item') : null;
			if (!li) {
				return;
			}
			var ol = li.parentElement;
			if (!ol || !ol.classList.contains('sikshya-ordering')) {
				return;
			}
			e.preventDefault();
			moveLi(ol, li, e.key === 'ArrowUp' ? -1 : 1);
			refocusOrderingButton(li, e.key === 'ArrowUp' ? '.sikshya-ordering__up' : '.sikshya-ordering__down');
		});

		// Make ordering items keyboard-reachable so the alt-arrow shortcut
		// has something to focus. Buttons already are focusable.
		var items = form.querySelectorAll('.sikshya-ordering__item');
		Array.prototype.forEach.call(items, function (li) {
			if (!li.hasAttribute('tabindex')) {
				li.setAttribute('tabindex', '0');
			}
		});
	}

	function refocusOrderingButton(li, sel) {
		// After DOM reorder, the same `li` reference is still valid — restore
		// focus to its move button so successive presses keep working.
		var btn = li.querySelector(sel);
		if (btn && typeof btn.focus === 'function') {
			btn.focus();
		}
	}

	// Walk the form and return a list of unanswered question payloads:
	// `{ index, label, block }`. Index is 1-based for human messaging.
	// Returns [] when everything is answered.
	function validateForm(form) {
		var missing = [];
		var blocks = form.querySelectorAll('.sikshya-q');
		Array.prototype.forEach.call(blocks, function (block, i) {
			if (block.hidden) {
				// One-per-page: still validate hidden ones; learner expects
				// "go back and answer Q3" feedback.
			}
			var qid = block.getAttribute('data-qid');
			var qtype = block.getAttribute('data-qtype') || '';
			if (!qid) {
				return;
			}
			var label = block.getAttribute('data-q-label') || ('Question ' + (i + 1));
			var answered = false;

			if (qtype === 'multiple_response') {
				answered = !!block.querySelector('input[type="checkbox"]:checked');
			} else if (qtype === 'ordering') {
				// Ordering is "always answered" — submitting the existing
				// order is a valid attempt. Don't gate.
				answered = !!block.querySelector('.sikshya-ordering li');
			} else if (qtype === 'matching') {
				var selects = block.querySelectorAll('.sikshya-matching select');
				if (selects.length === 0) {
					answered = true;
				} else {
					answered = Array.prototype.every.call(selects, function (s) {
						return s.value && s.value !== '';
					});
				}
			} else if (qtype === 'true_false' || qtype === 'multiple_choice') {
				answered = !!block.querySelector('input[type="radio"]:checked');
			} else if (qtype === 'short_answer' || qtype === 'essay' || qtype === 'fill_blank') {
				var ta = block.querySelector('textarea, input[type="text"]');
				answered = !!(ta && ta.value && ta.value.replace(/\s+/g, '') !== '');
			} else {
				// Unknown type — don't block.
				answered = true;
			}

			if (!answered) {
				missing.push({ index: i + 1, label: label, block: block });
			}
		});
		return missing;
	}

	function showUnansweredError(form, missing) {
		var cfg = getConfig();
		var resultEl = form.querySelector('.sikshya-quiz-result');
		if (!resultEl) {
			return;
		}
		var i18n = (cfg && cfg.i18n) ? cfg.i18n : {};
		var heading = i18n.unansweredHeading || 'Please answer every question before submitting.';
		var nums = missing.map(function (m) { return '#' + m.index; }).join(', ');
		resultEl.hidden = false;
		resultEl.classList.add('sikshya-quiz-result--error');
		resultEl.innerHTML = '';
		var h = document.createElement('p');
		h.className = 'sikshya-quiz-result__heading';
		h.textContent = heading;
		var p = document.createElement('p');
		p.className = 'sikshya-quiz-result__missing';
		p.textContent = (i18n.unansweredList || 'Unanswered:') + ' ' + nums;
		resultEl.appendChild(h);
		resultEl.appendChild(p);
		// Scroll first miss into view + focus first input.
		if (missing.length && missing[0].block && typeof missing[0].block.scrollIntoView === 'function') {
			missing[0].block.scrollIntoView({ behavior: 'smooth', block: 'center' });
			var firstFocusable = missing[0].block.querySelector('input, textarea, select, button');
			if (firstFocusable && typeof firstFocusable.focus === 'function') {
				try { firstFocusable.focus({ preventScroll: true }); } catch (err) { firstFocusable.focus(); }
			}
		}
	}

	function renderResult(el, data) {
		if (!data) {
			el.textContent = '';
			el.hidden = true;
			return;
		}
		var cfg = getConfig();
		var pct = typeof data.score_percent === 'number' ? data.score_percent : 0;
		var passed = !!data.passed;
		var passing = typeof data.passing_score === 'number' ? data.passing_score : null;
		var i18n = (cfg && cfg.i18n) ? cfg.i18n : {};
		var msg = passed ? (i18n.passed || 'You passed this quiz.') : (i18n.notPassed || 'You did not reach the passing score.');
		el.innerHTML = '';
		el.classList.add('sikshya-quiz-result--panel');

		var wrap = document.createElement('div');
		wrap.className = 'sikshya-quizResults';

		var title = document.createElement('h3');
		title.className = 'sikshya-quizResults__title';
		title.textContent = i18n.resultsTitle || 'Quiz results';
		wrap.appendChild(title);

		var score = document.createElement('p');
		score.className = 'sikshya-quizResults__score';
		score.textContent = i18n.score ? i18n.score.replace('%s', String(pct)) : ('Score: ' + String(pct) + '%');
		wrap.appendChild(score);

		if (passing !== null) {
			var req = document.createElement('p');
			req.className = 'sikshya-quizResults__req';
			req.textContent = i18n.passingScore ? i18n.passingScore.replace('%s', String(passing)) : ('Passing score: ' + String(passing) + '%');
			wrap.appendChild(req);
		}

		var status = document.createElement('p');
		status.className = passed ? 'sikshya-quizResults__pass' : 'sikshya-quizResults__fail';
		status.textContent = msg;
		wrap.appendChild(status);

		// Per-question-type score breakdown. Buckets each question into
		// its type and emits a tidy "Multiple choice: 4/5 · 80%" row so
		// learners can see where they slipped vs. where they were strong.
		(function renderBreakdown() {
			var perQ = data && data.per_question_results;
			if (!perQ || typeof perQ !== 'object') { return; }
			var keys = Object.keys(perQ);
			if (!keys.length) { return; }
			var buckets = {};
			keys.forEach(function (qid) {
				var r = perQ[qid] || {};
				var t = r.type || 'unknown';
				if (!buckets[t]) { buckets[t] = { count: 0, correct: 0, earned: 0, possible: 0, hasGrading: false }; }
				var b = buckets[t];
				b.count++;
				b.earned += Number(r.earned) || 0;
				b.possible += Number(r.possible) || 0;
				if (r.correct === true) { b.correct++; }
				if (r.correct !== null && r.correct !== undefined) { b.hasGrading = true; }
			});

			var typeNames = (cfg && cfg.i18n && cfg.i18n.questionTypeNames) || {};
			function labelFor(key) {
				if (typeNames[key]) { return typeNames[key]; }
				// Reasonable fallback labels — keep matched with question type slugs.
				var fallback = {
					multiple_choice: 'Multiple choice',
					multiple_response: 'Multiple response',
					true_false: 'True / False',
					short_answer: 'Short answer',
					essay: 'Essay',
					fill_blank: 'Fill in the blank',
					ordering: 'Ordering',
					matching: 'Matching',
				};
				return fallback[key] || (key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '));
			}

			var section = document.createElement('div');
			section.className = 'sikshya-quizResults__breakdown';
			var bhead = document.createElement('p');
			bhead.className = 'sikshya-quizResults__breakdownHead';
			bhead.textContent = (cfg && cfg.i18n && cfg.i18n.breakdownTitle) || 'Score breakdown';
			section.appendChild(bhead);

			Object.keys(buckets).forEach(function (k) {
				var b = buckets[k];
				var row = document.createElement('div');
				row.className = 'sikshya-quizResults__breakdownRow';
				var name = document.createElement('span');
				name.className = 'sikshya-quizResults__breakdownLabel';
				name.textContent = labelFor(k);
				var detail = document.createElement('span');
				detail.className = 'sikshya-quizResults__breakdownDetail';
				if (!b.hasGrading) {
					// Essay-only bucket — no auto-grade signal.
					detail.textContent = b.count + ' ' + ((cfg && cfg.i18n && cfg.i18n.manualGrading) || '(awaiting review)');
				} else {
					var pctLocal = b.possible > 0
						? Math.round((b.earned / b.possible) * 100)
						: (b.count > 0 ? Math.round((b.correct / b.count) * 100) : 0);
					detail.textContent = b.correct + '/' + b.count + ' · ' + pctLocal + '%';
				}
				row.appendChild(name);
				row.appendChild(detail);
				section.appendChild(row);
			});

			wrap.appendChild(section);
		})();

		var actions = document.createElement('div');
		actions.className = 'sikshya-quizResults__actions';

		if (cfg && cfg.nextUrl) {
			var cont = document.createElement('a');
			cont.href = cfg.nextUrl;
			cont.className = 'sikshya-btn sikshya-btn--primary';
			cont.textContent = i18n.continue || 'Continue';
			actions.appendChild(cont);
		}

		var toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.className = 'sikshya-btn sikshya-btn--outline';
		toggle.textContent = i18n.reviewAnswers || 'Review answers';
		actions.appendChild(toggle);

		var again = document.createElement('button');
		again.type = 'button';
		again.className = 'sikshya-btn sikshya-btn--ghost';
		again.textContent = i18n.tryAgain || 'Try again';
		actions.appendChild(again);

		wrap.appendChild(actions);
		el.appendChild(wrap);
		el.hidden = false;

		// Default behavior: show a dedicated results UI, not the next screen.
		// Keep answers available via "Review answers".
		var form = el.closest ? el.closest('form') : null;
		if (form) {
			var blocks = form.querySelectorAll('.sikshya-q');
			// Inject per-question explanations if the server sent any.
			// Stored under `_sikshya_question_explanation` on the question
			// post; rendered after grading inside the answered block.
			var explanations = (data && data.per_question_explanations && typeof data.per_question_explanations === 'object')
				? data.per_question_explanations
				: null;
			Array.prototype.forEach.call(blocks, function (b) {
				b.hidden = true;
				var inputs = b.querySelectorAll('input, textarea, select, button');
				Array.prototype.forEach.call(inputs, function (n) {
					if (n && n.tagName !== 'BUTTON') {
						n.disabled = true;
					}
				});
				if (!explanations) { return; }
				var qid = b.getAttribute('data-qid');
				if (!qid) { return; }
				var html = explanations[qid] || explanations[String(qid)] || '';
				if (!html || b.querySelector('.sikshya-quizQ__explanation')) { return; }
				var det = document.createElement('details');
				det.className = 'sikshya-quizQ__explanation';
				var sum = document.createElement('summary');
				sum.className = 'sikshya-quizQ__explanationSummary';
				sum.textContent = (cfg && cfg.i18n && cfg.i18n.explanationLabel) || 'Why?';
				var body = document.createElement('div');
				body.className = 'sikshya-quizQ__explanationBody';
				body.innerHTML = html;
				det.appendChild(sum);
				det.appendChild(body);
				b.appendChild(det);
			});
			var pager = form.querySelector('[data-sikshya-quiz-pager]');
			if (pager) {
				pager.hidden = true;
			}
			var submit = form.querySelector('.sikshya-quiz-submit');
			if (submit) {
				submit.hidden = true;
			}
		}

		var showing = false;
		toggle.addEventListener('click', function () {
			showing = !showing;
			toggle.textContent = showing ? (i18n.hideAnswers || 'Hide answers') : (i18n.reviewAnswers || 'Review answers');
			if (!form) {
				return;
			}
			var blocks = form.querySelectorAll('.sikshya-q');
			Array.prototype.forEach.call(blocks, function (b) {
				b.hidden = !showing;
			});
		});

		again.addEventListener('click', function () {
			window.location.reload();
		});
	}

	function onSubmit(e) {
		e.preventDefault();
		if (isSubmitting) {
			return;
		}
		isSubmitting = true;
		var cfg = getConfig();
		var form = e.target;
		var resultEl = form.querySelector('.sikshya-quiz-result');
		var btn = form.querySelector('.sikshya-quiz-submit');
		if (!cfg.restUrl || !cfg.restNonce || !cfg.quizId) {
			if (resultEl) {
				resultEl.hidden = false;
				resultEl.textContent = (cfg && cfg.i18n && cfg.i18n.error) || 'Could not submit the quiz. Please reload the page and try again.';
			}
			return;
		}
		// Pre-flight: don't submit a partially-answered quiz silently. The
		// REST endpoint accepts it (graded as wrong), but learners
		// generally want a chance to revisit a missed question first.
		var missing = validateForm(form);
		if (missing.length > 0) {
			isSubmitting = false;
			if (btn) {
				btn.disabled = false;
			}
			showUnansweredError(form, missing);
			return;
		}
		// Clear any prior validation chrome on success-path.
		if (resultEl) {
			resultEl.classList.remove('sikshya-quiz-result--error');
		}

		if (btn) {
			btn.disabled = true;
		}

		var body = {
			quiz_id: parseInt(cfg.quizId, 10),
			answers: collectAnswers(form),
			question_ids: collectQuestionOrder(form),
			time_taken: window.__sikshyaQuizTimeTakenSeconds || 0,
			attempt_id: attemptId || 0,
		};

		fetch(buildSikshyaRestEndpointUrl(cfg.restUrl, 'me/quiz-submit'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce,
			},
			body: JSON.stringify(body),
		})
			.then(function (r) {
				return r.json().then(function (j) {
					return { ok: r.ok, status: r.status, json: j };
				});
			})
			.then(function (res) {
				try {
					if (!res.ok) {
						console.warn('[Sikshya quiz-submit]', res.status, res.json);
					}
				} catch (e) {}
				if (res.ok && res.json && res.json.ok && res.json.data) {
					// Successful submit → stop the auto-save loop; the
					// answers_data column now owns the canonical record.
					if (attemptId) { stopAutoSave(attemptId); }
					renderResult(resultEl, res.json.data);
				} else {
					var err = (res.json && res.json.message) ? res.json.message : ((window.sikshyaQuizTaker && window.sikshyaQuizTaker.i18n && window.sikshyaQuizTaker.i18n.error) || 'Could not submit quiz.');
					if (resultEl) {
						resultEl.hidden = false;
						resultEl.textContent = err;
					}
				}
			})
			.catch(function () {
				if (resultEl) {
					resultEl.hidden = false;
					resultEl.textContent = (window.sikshyaQuizTaker && window.sikshyaQuizTaker.i18n && window.sikshyaQuizTaker.i18n.error) || 'Could not submit quiz.';
				}
			})
			.finally(function () {
				isSubmitting = false;
				if (btn) {
					btn.disabled = false;
				}
			});
	}

	// ── Mid-attempt auto-save ──────────────────────────────────────────
	// Map of attemptId → { timer, lastJson, form } so an autosave loop can be
	// cleared when the form is submitted. One quiz per page in practice; the
	// map is just defensive.
	var autoSaveState = {};

	function autoSaveAnswers(form, cfg, attempt_id) {
		if (!attempt_id || !cfg.restUrl || !cfg.restNonce || !cfg.quizId) {
			return;
		}
		var payload = collectAnswers(form);
		var json = '';
		try { json = JSON.stringify(payload); } catch (e) { json = ''; }
		var state = autoSaveState[attempt_id] || {};
		if (state.lastJson === json) {
			// Nothing changed since last save — skip the network hit.
			return;
		}
		state.lastJson = json;
		autoSaveState[attempt_id] = state;
		fetch(buildSikshyaRestEndpointUrl(cfg.restUrl, 'me/quiz-save'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
			body: JSON.stringify({
				quiz_id: parseInt(cfg.quizId, 10) || 0,
				attempt_id: parseInt(attempt_id, 10) || 0,
				answers: payload,
			}),
		}).catch(function () {
			// Network blip — drop `lastJson` so the next change retries the
			// same payload instead of being skipped as "no change".
			var s = autoSaveState[attempt_id];
			if (s) { s.lastJson = null; }
		});
	}

	function startAutoSave(form, cfg, attempt_id) {
		if (!attempt_id || autoSaveState[attempt_id] && autoSaveState[attempt_id].timer) {
			return;
		}
		var state = autoSaveState[attempt_id] || {};
		state.form = form;
		state.timer = window.setInterval(function () {
			autoSaveAnswers(form, cfg, attempt_id);
		}, 30000);
		autoSaveState[attempt_id] = state;

		// Debounced save on every change/input to capture work between ticks.
		var debounce = null;
		var onMutate = function () {
			if (debounce) { window.clearTimeout(debounce); }
			debounce = window.setTimeout(function () {
				autoSaveAnswers(form, cfg, attempt_id);
			}, 1500);
		};
		form.addEventListener('change', onMutate);
		form.addEventListener('input', onMutate);
	}

	function stopAutoSave(attempt_id) {
		var state = autoSaveState[attempt_id];
		if (state && state.timer) {
			window.clearInterval(state.timer);
			state.timer = null;
		}
	}

	// Hydrate the form from the server-supplied auto-save snapshot. Walks
	// each .sikshya-q block by qid and restores radios, checkboxes,
	// textareas, ordering order, and matching select values.
	function hydrateForm(form, data) {
		if (!form || !data || typeof data !== 'object') {
			return false;
		}
		var hydrated = false;
		var blocks = form.querySelectorAll('.sikshya-q');
		Array.prototype.forEach.call(blocks, function (block) {
			var qid = block.getAttribute('data-qid');
			var qtype = block.getAttribute('data-qtype') || '';
			if (!qid || !(qid in data) && !(parseInt(qid, 10) in data)) {
				return;
			}
			var saved = (qid in data) ? data[qid] : data[parseInt(qid, 10)];
			if (saved === null || saved === undefined) { return; }

			if (qtype === 'multiple_response' && Array.isArray(saved)) {
				var checks = block.querySelectorAll('input[type="checkbox"]');
				Array.prototype.forEach.call(checks, function (c) {
					c.checked = saved.indexOf(c.value) !== -1;
				});
				hydrated = true;
				return;
			}
			if ((qtype === 'multiple_choice' || qtype === 'true_false') && typeof saved !== 'object') {
				var radio = block.querySelector('input[type="radio"][value="' + String(saved).replace(/"/g, '\\"') + '"]');
				if (radio) {
					radio.checked = true;
					hydrated = true;
				}
				return;
			}
			if ((qtype === 'short_answer' || qtype === 'essay' || qtype === 'fill_blank') && typeof saved === 'string') {
				var ta = block.querySelector('textarea, input[type="text"]');
				if (ta) {
					ta.value = saved;
					hydrated = true;
				}
				return;
			}
			if (qtype === 'matching' && saved && typeof saved === 'object') {
				Object.keys(saved).forEach(function (k) {
					var sel = block.querySelector('.sikshya-matching select[data-left="' + k + '"]')
						|| block.querySelector('.sikshya-matching select[name*="[' + k + ']"]');
					if (sel) {
						sel.value = saved[k];
						hydrated = true;
					}
				});
				return;
			}
			if (qtype === 'ordering' && Array.isArray(saved)) {
				var ol = block.querySelector('.sikshya-ordering');
				if (!ol) { return; }
				// Reorder <li>s by data-item-index matching the saved order.
				saved.forEach(function (ix) {
					var li = ol.querySelector('li[data-item-index="' + String(ix).replace(/"/g, '\\"') + '"]');
					if (li) { ol.appendChild(li); }
				});
				hydrated = true;
				return;
			}
		});
		return hydrated;
	}

	function init() {
		var cfg = getConfig();
		var locked = !!(cfg && (cfg.locked || (cfg.attempt && cfg.attempt.status === 'locked')));
		duration = parseInt(cfg.durationSeconds || (cfg.advanced && cfg.advanced.durationSeconds) || 0, 10) || 0;
		var timerEl = document.querySelector('[data-sikshya-quiz-timer-value]');
		var startBtns = document.querySelectorAll('[data-sikshya-quiz-start]');
		var quizForm = document.querySelector('[data-sikshya-quiz-form]');
		var quizIntro = document.querySelector('[data-sikshya-quiz-intro]');
		var countdownTimer = null;

		function ensureTimerEl() {
			if (timerEl) return timerEl;
			var host = document.querySelector('[data-sikshya-quiz-timer]');
			if (!host) return null;
			var el = host.querySelector('[data-sikshya-quiz-timer-value]');
			if (!el) {
				el = document.createElement('span');
				el.className = 'sikshya-quizTimer__value';
				el.setAttribute('data-sikshya-quiz-timer-value', '');
				host.appendChild(el);
			}
			timerEl = el;
			return timerEl;
		}

		function fmt(secs) {
			secs = Math.max(0, parseInt(secs, 10) || 0);
			var h = Math.floor(secs / 3600);
			var m = Math.floor((secs % 3600) / 60);
			var s = secs % 60;
			var hh = h < 10 ? '0' + String(h) : String(h);
			var mm = m < 10 ? '0' + String(m) : String(m);
			var ss = s < 10 ? '0' + String(s) : String(s);
			// Always show HH:MM:SS for clarity.
			return hh + ':' + mm + ':' + ss;
		}

		function tick() {
			if (locked) {
				return;
			}
			if (!duration || !startedAt) {
				return;
			}
			var now = Date.now() + serverOffsetMs;
			var elapsed = Math.floor((now - startedAt) / 1000);
			window.__sikshyaQuizTimeTakenSeconds = elapsed;
			var left = duration - elapsed;
			var te = ensureTimerEl();
			if (te) {
				te.textContent = fmt(left);
			}
			if (left <= 0) {
				if (countdownTimer) {
					clearInterval(countdownTimer);
					countdownTimer = null;
				}
				if (autoSubmitted) {
					return;
				}
				autoSubmitted = true;
				// Auto-submit when time ends.
				try {
					if (quizForm) {
						var submitBtn = quizForm.querySelector('.sikshya-quiz-submit');
						if (submitBtn && !submitBtn.disabled) {
							submitBtn.click();
						} else {
							quizForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
						}
					}
				} catch (e) {
					/* ignore */
				}
			}
		}

		function startTimer() {
			if (locked) return;
			if (!duration) return;
			if (countdownTimer) return;
			if (!startedAt) {
				// Fresh start: anchor on "now" (server-adjusted).
				startedAt = Date.now() + serverOffsetMs;
				window.__sikshyaQuizTimeTakenSeconds = 0;
				// Show immediately at full duration (e.g. 10:00), then start ticking.
				var te = ensureTimerEl();
				if (te) {
					te.textContent = fmt(duration);
				}
			}
			tick();
			countdownTimer = setInterval(tick, 1000);
		}

		function parseMysqlToMs(s) {
			// "YYYY-MM-DD HH:MM:SS" (site timezone). We treat it as local time for countdown purposes.
			if (!s || typeof s !== 'string') return 0;
			var m = s.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/);
			if (!m) return 0;
			var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]), Number(m[6]));
			return d.getTime();
		}

		function fetchActiveAttempt() {
			// Prefer server-rendered attempt data (no page-load REST call).
			if (cfg && cfg.attempt && cfg.serverTime) {
				return Promise.resolve({ ok: true, json: { ok: true, data: { attempt: cfg.attempt, durationSeconds: cfg.durationSeconds || 0, serverTime: cfg.serverTime } } });
			}
			if (!cfg.restUrl || !cfg.restNonce || !cfg.quizId) return Promise.resolve(null);
			var url = buildSikshyaRestEndpointUrl(cfg.restUrl, 'me/quiz-attempt', { quiz_id: cfg.quizId });
			return fetch(url, { method: 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.restNonce } })
				.then(function (r) {
					return r.json().then(function (j) {
						return { ok: r.ok, json: j };
					});
				})
				.catch(function () { return null; });
		}

		function startAttemptOnServer() {
			if (!cfg.restUrl || !cfg.restNonce || !cfg.quizId) return Promise.resolve(null);
			return fetch(buildSikshyaRestEndpointUrl(cfg.restUrl, 'me/quiz-attempt'), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
				body: JSON.stringify({ quiz_id: parseInt(cfg.quizId, 10) || 0 }),
			})
				.then(function (r) {
					return r.json().then(function (j) {
						return { ok: r.ok, json: j };
					});
				})
				.catch(function () { return null; });
		}

		function applyAttemptPayload(payload) {
			if (!payload || !payload.ok || !payload.json || payload.json.ok !== true) return;
			var d = payload.json.data || {};
			if (typeof d.serverTime === 'number') {
				serverOffsetMs = d.serverTime * 1000 - Date.now();
			}
			if (d.durationSeconds) {
				duration = parseInt(d.durationSeconds, 10) || duration;
			}
			var a = d.attempt || null;
			var hydrationSnapshot = null;
			if (a && typeof a === 'object') {
				attemptId = parseInt(a.id, 10) || attemptId;
				var ts = parseInt(a.started_at_ts || 0, 10) || 0;
				if (ts > 0) {
					startedAt = ts * 1000;
				} else {
					var ms = parseMysqlToMs(String(a.started_at || ''));
					if (ms) startedAt = ms;
				}
				if (a.auto_save_data && typeof a.auto_save_data === 'object') {
					hydrationSnapshot = a.auto_save_data;
				}
			} else if (d.attempt_id) {
				attemptId = parseInt(d.attempt_id, 10) || attemptId;
				var ts2 = parseInt(d.started_at_ts || 0, 10) || 0;
				if (ts2 > 0) {
					startedAt = ts2 * 1000;
				} else {
					var ms2 = parseMysqlToMs(String(d.started_at || ''));
					if (ms2) startedAt = ms2;
				}
			}

			// If the server gave us a saved snapshot, restore it into the
			// already-mounted form. Quiet — no toast, just makes the form
			// reflect what the learner had typed.
			if (hydrationSnapshot && quizForm) {
				hydrateForm(quizForm, hydrationSnapshot);
			}
		}

		function startQuizUi() {
			if (!quizForm) {
				return;
			}
			quizForm.hidden = false;
			if (quizIntro) {
				quizIntro.setAttribute('hidden', '');
			}
			if (startBtns && startBtns.length) {
				Array.prototype.forEach.call(startBtns, function (b) {
					b.setAttribute('hidden', '');
					b.setAttribute('aria-expanded', 'true');
				});
			}
			try {
				var first = quizForm.querySelector('input, textarea, select, button');
				if (first && first.focus) {
					first.focus({ preventScroll: true });
				}
				if (quizForm.scrollIntoView) {
					quizForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			} catch (e) {
				/* ignore */
			}
		}

		// Start on "Start quiz" click.
		if (startBtns && startBtns.length) {
			Array.prototype.forEach.call(startBtns, function (b) {
				b.addEventListener('click', function () {
					if (locked) {
						return;
					}
					if (b.hasAttribute('disabled') || b.getAttribute('aria-disabled') === 'true') {
						return;
					}
					startQuizUi();
					// Ensure attempt exists server-side and timer resumes across browsers.
					startAttemptOnServer().then(function (res) {
						applyAttemptPayload(res);
						if (duration) {
							window.setTimeout(startTimer, 50);
						}
					});
				});
			});
		}

		// If form is already visible (resume), start immediately.
		if (quizForm && quizForm.hidden === false) {
			if (locked) {
				return;
			}
			fetchActiveAttempt().then(function (res) {
				applyAttemptPayload(res);
				if (attemptId && startedAt) {
					if (duration) startTimer();
					return;
				}
				// Form is visible but no active attempt was found (or template didn't include it).
				// Create/reuse an attempt so the timer is server-anchored, then start ticking.
				startAttemptOnServer().then(function (res2) {
					applyAttemptPayload(res2);
					if (duration) startTimer();
				});
			});
		} else if (quizForm) {
			// If an in-progress attempt exists, auto-resume without requiring the user to click "Start quiz" again.
			fetchActiveAttempt().then(function (res) {
				applyAttemptPayload(res);
				if (attemptId && startedAt) {
					if (locked) {
						return;
					}
					startQuizUi();
					if (duration) startTimer();
				}
			});
		}

		var forms = document.querySelectorAll('form.sikshya-quiz-form[data-quiz-id]');
		Array.prototype.forEach.call(forms, function (form) {
			bindOrdering(form);
			setupOnePerPage(form);
			form.addEventListener('submit', onSubmit);
			// Kick off auto-save as soon as we know which attempt this is.
			// If attemptId arrives later (via fetchActiveAttempt), the
			// attempt-payload application below will re-call this with the
			// real id.
			if (attemptId) {
				startAutoSave(form, cfg, attemptId);
			}
		});

		// Watch for attemptId becoming known after async start. Cheap polling
		// avoids reaching into the Promise chains above just to wire one call.
		var arm = window.setInterval(function () {
			if (attemptId && quizForm) {
				startAutoSave(quizForm, cfg, attemptId);
				window.clearInterval(arm);
			}
		}, 250);
		window.setTimeout(function () { window.clearInterval(arm); }, 15000);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
