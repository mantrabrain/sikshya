(function () {
	'use strict';

	function getConfig() {
		return window.sikshyaQuizTaker || {};
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
		prev.textContent = 'Previous';
		var next = document.createElement('button');
		next.type = 'button';
		next.className = 'sikshya-btn sikshya-btn--primary sikshya-btn--sm sikshya-quizPager__next';
		next.textContent = 'Next';
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
			} else if (t.classList.contains('sikshya-ordering__down')) {
				e.preventDefault();
				moveLi(ol, li, 1);
			}
		});
	}

	function renderResult(el, data) {
		if (!data) {
			el.textContent = '';
			el.hidden = true;
			return;
		}
		var pct = typeof data.score_percent === 'number' ? data.score_percent : 0;
		var passed = !!data.passed;
		var msg = passed
			? (window.sikshyaQuizTaker && window.sikshyaQuizTaker.i18n && window.sikshyaQuizTaker.i18n.passed) || 'Passed.'
			: (window.sikshyaQuizTaker && window.sikshyaQuizTaker.i18n && window.sikshyaQuizTaker.i18n.notPassed) || 'Not passed.';
		el.innerHTML = '';
		var p = document.createElement('p');
		p.className = 'sikshya-quiz-result__score';
		p.textContent = (window.sikshyaQuizTaker && window.sikshyaQuizTaker.i18n && window.sikshyaQuizTaker.i18n.score)
			? window.sikshyaQuizTaker.i18n.score.replace('%s', String(pct))
			: ('Score: ' + String(pct) + '%');
		el.appendChild(p);
		var p2 = document.createElement('p');
		p2.className = passed ? 'sikshya-quiz-result__pass' : 'sikshya-quiz-result__fail';
		p2.textContent = msg;
		el.appendChild(p2);
		el.hidden = false;
	}

	function onSubmit(e) {
		e.preventDefault();
		var cfg = getConfig();
		if (!cfg.restUrl || !cfg.restNonce || !cfg.quizId) {
			return;
		}
		var form = e.target;
		var resultEl = form.querySelector('.sikshya-quiz-result');
		var btn = form.querySelector('.sikshya-quiz-submit');
		if (btn) {
			btn.disabled = true;
		}

		var body = {
			quiz_id: parseInt(cfg.quizId, 10),
			answers: collectAnswers(form),
			question_ids: collectQuestionOrder(form),
			time_taken: 0,
		};

		fetch(cfg.restUrl + 'me/quiz-submit', {
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
				if (res.ok && res.json && res.json.ok && res.json.data) {
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
				if (btn) {
					btn.disabled = false;
				}
			});
	}

	function init() {
		var forms = document.querySelectorAll('form.sikshya-quiz-form[data-quiz-id]');
		Array.prototype.forEach.call(forms, function (form) {
			bindOrdering(form);
			setupOnePerPage(form);
			form.addEventListener('submit', onSubmit);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
