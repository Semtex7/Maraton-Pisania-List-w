/**
 * Report survey in the organiser panel. The form is loaded over AJAX (para.js), so everything
 * here uses delegated listeners on the document.
 */
(function () {
    'use strict';

    var FORM_ID = 'ajax-report-form';
    var OTHER = '__other__';

    function surveyForm(target) {
        var form = target && target.closest ? target.closest('form') : null;
        return form && form.id === FORM_ID ? form : null;
    }

    function selectedValues(question) {
        return Array.prototype.map.call(
            question.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked'),
            function (input) { return input.value; }
        );
    }

    function setEnabled(question, enabled) {
        question.hidden = !enabled;
        question.querySelectorAll('input, textarea, select').forEach(function (field) {
            if (!field.classList.contains('survey-other')) {
                field.disabled = !enabled;
            }
        });
    }

    function updateOther(question) {
        var otherInput = question.querySelector('.survey-other');
        if (!otherInput) return;
        var active = !question.hidden && selectedValues(question).indexOf(OTHER) !== -1;
        otherInput.hidden = !active;
        otherInput.disabled = !active;
        otherInput.required = active;
    }

    // Questions are checked in page order, so a question depending on a hidden one stays hidden too
    function updateConditions(form) {
        form.querySelectorAll('.survey-question').forEach(function (question) {
            var parentId = question.getAttribute('data-condition-question');
            if (parentId) {
                var parent = form.querySelector('.survey-question[data-question="' + parentId + '"]');
                var visible = !!parent && !parent.hidden &&
                    selectedValues(parent).indexOf(question.getAttribute('data-condition-value')) !== -1;
                setEnabled(question, visible);
            }
            updateOther(question);
        });
    }

    function toNumber(input) {
        var value = parseInt(input.value, 10);
        return isNaN(value) || value < 0 ? 0 : value;
    }

    function updateLetters(table) {
        var totals = { authorities: 0, solidarity: 0 };
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var rowTotal = 0;
            row.querySelectorAll('input[data-letters]').forEach(function (input) {
                var value = toNumber(input);
                totals[input.getAttribute('data-letters')] += value;
                rowTotal += value;
            });
            row.querySelector('[data-letters-row-total]').textContent = rowTotal;
        });
        table.querySelector('[data-letters-total="authorities"]').textContent = totals.authorities;
        table.querySelector('[data-letters-total="solidarity"]').textContent = totals.solidarity;
        table.querySelector('[data-letters-total="all"]').textContent = totals.authorities + totals.solidarity;
    }

    document.addEventListener('change', function (e) {
        var form = surveyForm(e.target);
        if (form && (e.target.type === 'radio' || e.target.type === 'checkbox')) {
            updateConditions(form);
        }
    });

    document.addEventListener('input', function (e) {
        var table = e.target.closest ? e.target.closest('.survey-letters-table') : null;
        if (table && surveyForm(table)) {
            updateLetters(table);
        }
    });

    // Capture phase: runs before the panel's own submit handler and stops it when a required
    // checkbox group is empty (browsers cannot require "at least one" natively)
    document.addEventListener('submit', function (e) {
        var form = surveyForm(e.target);
        if (!form) return;

        var missing = Array.prototype.find.call(
            form.querySelectorAll('.survey-question[data-type="checkbox"][data-required]'),
            function (question) { return !question.hidden && selectedValues(question).length === 0; }
        );
        if (!missing) return;

        e.preventDefault();
        e.stopPropagation();

        var legend = missing.querySelector('legend');
        var status = document.getElementById('report-status-message');
        if (status) {
            status.style.color = 'red';
            status.textContent = 'Zaznacz co najmniej jedną odpowiedź w pytaniu: „' +
                (legend ? legend.textContent.replace(/\s*\*\s*$/, '') : '') + '”.';
        }
        missing.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var first = missing.querySelector('input[type="checkbox"]');
        if (first) first.focus({ preventScroll: true });
    }, true);
})();
