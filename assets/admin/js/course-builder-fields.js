/**
 * Course builder: conditional fields + repeater groups.
 *
 * @package Sikshya
 */
(function () {
    'use strict';

    function builderForm() {
        return document.getElementById('sikshya-course-builder-form');
    }

    function getControllerValue(form, name) {
        if (!form || !name) {
            return '';
        }
        const el = form.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
        if (!el) {
            return '';
        }
        if (el.type === 'checkbox') {
            return el.checked ? '1' : '';
        }
        if (el.tagName === 'SELECT' && el.multiple) {
            return '';
        }
        return el.value;
    }

    function rowDependsMet(form, row) {
        const allRaw = row.getAttribute('data-sikshya-depends-all');
        if (allRaw) {
            let rules;
            try {
                rules = JSON.parse(allRaw);
            } catch (e) {
                return true;
            }
            if (!Array.isArray(rules)) {
                return true;
            }
            return rules.every(function (r) {
                if (!r || !r.on) {
                    return true;
                }
                const val = getControllerValue(form, r.on);
                if (Object.prototype.hasOwnProperty.call(r, 'value')) {
                    return String(val) === String(r.value);
                }
                return val === '1';
            });
        }

        const on = row.getAttribute('data-sikshya-depends-on');
        if (!on) {
            return true;
        }
        const v = getControllerValue(form, on);
        const single = row.getAttribute('data-sikshya-depends-value');
        const multi = row.getAttribute('data-sikshya-depends-values');
        if (multi) {
            try {
                const arr = JSON.parse(multi);
                return Array.isArray(arr) && arr.indexOf(String(v)) !== -1;
            } catch (e2) {
                return true;
            }
        }
        if (single !== null && single !== '') {
            return String(v) === single;
        }
        return v === '1';
    }

    function refreshConditionalFields() {
        const form = builderForm();
        if (!form) {
            return;
        }
        form.querySelectorAll('.sikshya-form-row--field').forEach(function (row) {
            if (!row.hasAttribute('data-sikshya-depends-on') && !row.hasAttribute('data-sikshya-depends-all')) {
                return;
            }
            const show = rowDependsMet(form, row);
            row.classList.toggle('sikshya-form-row--hidden', !show);
        });
    }

    function repeaterGroupAdd(fieldId) {
        const container = document.getElementById(fieldId + '_group');
        if (!container) {
            return;
        }
        const rows = container.querySelectorAll('.sikshya-repeater-group-row');
        const template = rows[0];
        if (!template) {
            return;
        }
        const idx = rows.length;
        const clone = template.cloneNode(true);
        clone.querySelectorAll('input, textarea').forEach(function (el) {
            if (el.name) {
                el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
            }
            el.value = '';
        });
        container.appendChild(clone);
    }

    function repeaterGroupRemove(btn) {
        const row = btn.closest('.sikshya-repeater-group-row');
        const container = row && row.parentElement;
        if (!container) {
            return;
        }
        const rows = container.querySelectorAll('.sikshya-repeater-group-row');
        if (rows.length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input, textarea').forEach(function (el) {
                el.value = '';
            });
        }
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.sikshya-repeater-group-add');
        if (addBtn) {
            e.preventDefault();
            repeaterGroupAdd(addBtn.getAttribute('data-field-id') || '');
            return;
        }
        const rm = e.target.closest('.sikshya-repeater-group-remove');
        if (rm) {
            e.preventDefault();
            repeaterGroupRemove(rm);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const form = builderForm();
        if (!form) {
            return;
        }
        refreshConditionalFields();
        form.addEventListener('change', refreshConditionalFields);
        form.addEventListener('input', function (ev) {
            if (ev.target && ev.target.name) {
                refreshConditionalFields();
            }
        });
    });

    window.sikshyaRefreshCourseBuilderConditionals = refreshConditionalFields;
})();
