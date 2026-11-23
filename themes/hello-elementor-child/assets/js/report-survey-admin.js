/**
 * Survey builder: Wydarzenia → Ankieta raportu (events-report-survey.php).
 * Keeps the question list in memory and sends it as JSON on save; the server validates it again.
 */
(function () {
    'use strict';

    var root = document.getElementById('maraton-survey-builder');
    var form = document.getElementById('maraton-survey-form');
    if (!root || !form) return;

    var questions = JSON.parse(root.getAttribute('data-survey') || '[]');
    var types = JSON.parse(root.getAttribute('data-types') || '{}');
    var petitions = JSON.parse(root.getAttribute('data-petitions') || '[]');
    var dirty = false;
    var conditionRefreshers = [];

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            if (key === 'text') node.textContent = attrs[key];
            else if (key === 'className') node.className = attrs[key];
            else node.setAttribute(key, attrs[key]);
        });
        (children || []).forEach(function (child) { if (child) node.appendChild(child); });
        return node;
    }

    function isChoice(q) {
        return q.type === 'radio' || q.type === 'checkbox';
    }

    function changed() {
        dirty = true;
    }

    function newId() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var id;
        do {
            id = 'q_';
            for (var i = 0; i < 8; i++) id += chars.charAt(Math.floor(Math.random() * chars.length));
        } while (questions.some(function (q) { return q.id === id; }));
        return id;
    }

    function shortLabel(q) {
        var text = q.label || '(bez treści)';
        return text.length > 70 ? text.slice(0, 67) + '...' : text;
    }

    function render(focusIndex) {
        conditionRefreshers = [];
        root.textContent = '';
        root.appendChild(insertButton(0));
        questions.forEach(function (q, index) {
            root.appendChild(card(q, index));
            root.appendChild(insertButton(index + 1));
        });
        refreshConditions();

        if (typeof focusIndex === 'number') {
            var input = root.querySelectorAll('.msb-card')[focusIndex];
            input = input && input.querySelector('.msb-label');
            if (input) input.focus();
        }
    }

    function insertButton(position) {
        var button = el('button', { type: 'button', className: 'button-link msb-insert', text: '+ Dodaj pytanie tutaj' });
        button.addEventListener('click', function () {
            questions.splice(position, 0, {
                id: newId(), type: 'text', label: '', required: false, options: [], other: '', condition: null
            });
            changed();
            render(position);
        });
        return el('div', { className: 'msb-insert-row' }, [button]);
    }

    function card(q, index) {
        var isLetters = q.type === 'letters';
        var isHeading = q.type === 'heading';
        var node = el('div', { className: 'msb-card msb-type-' + q.type });

        // Header: number, type, order and delete
        var head = el('div', { className: 'msb-card-head' }, [el('span', { className: 'msb-num', text: String(index + 1) })]);
        if (isLetters) {
            head.appendChild(el('strong', { text: 'Tabela listów (bohaterowie_bohaterki)' }));
        } else {
            var typeSelect = el('select', { 'aria-label': 'Typ pytania' });
            Object.keys(types).forEach(function (key) {
                var option = el('option', { value: key, text: types[key] });
                if (key === q.type) option.selected = true;
                typeSelect.appendChild(option);
            });
            typeSelect.addEventListener('change', function () {
                q.type = typeSelect.value;
                if (q.type === 'heading') { q.required = false; q.condition = null; }
                changed();
                render();
            });
            head.appendChild(typeSelect);
        }

        var actions = el('span', { className: 'msb-actions' });
        var up = el('button', { type: 'button', className: 'button button-small', text: '↑', 'aria-label': 'Przesuń wyżej', title: 'Przesuń wyżej' });
        var down = el('button', { type: 'button', className: 'button button-small', text: '↓', 'aria-label': 'Przesuń niżej', title: 'Przesuń niżej' });
        up.disabled = index === 0;
        down.disabled = index === questions.length - 1;
        up.addEventListener('click', function () { move(index, -1); });
        down.addEventListener('click', function () { move(index, 1); });
        actions.appendChild(up);
        actions.appendChild(down);
        if (!isLetters) {
            var remove = el('button', { type: 'button', className: 'button-link button-link-delete', text: 'Usuń' });
            remove.addEventListener('click', function () { removeQuestion(index); });
            actions.appendChild(remove);
        }
        head.appendChild(actions);
        node.appendChild(head);

        // Label
        var labelInput = el('input', { type: 'text', className: 'large-text msb-label' });
        labelInput.value = q.label || '';
        labelInput.addEventListener('input', function () {
            q.label = labelInput.value;
            changed();
            refreshConditions();
        });
        node.appendChild(el('label', { className: 'msb-field' }, [
            el('span', { text: isHeading ? 'Tytuł sekcji' : 'Treść pytania' }),
            labelInput
        ]));

        if (isChoice(q)) {
            node.appendChild(choiceFields(q));
        }
        if (isLetters) {
            node.appendChild(lettersFields(q));
        }

        if (!isHeading && !isLetters) {
            var required = el('input', { type: 'checkbox' });
            required.checked = !!q.required;
            required.addEventListener('change', function () { q.required = required.checked; changed(); });
            node.appendChild(el('label', { className: 'msb-inline' }, [required, el('span', { text: ' Odpowiedź wymagana' })]));
        }

        if (!isHeading) {
            node.appendChild(conditionFields(q, index));
        }

        return node;
    }

    function choiceFields(q) {
        var wrap = el('div', { className: 'msb-choices' });

        var options = el('textarea', { rows: String(Math.max(3, (q.options || []).length + 1)), className: 'large-text' });
        options.value = (q.options || []).join('\n');
        options.addEventListener('input', function () {
            q.options = options.value.split('\n').map(function (line) { return line.trim(); }).filter(Boolean);
            changed();
            refreshConditions();
        });
        wrap.appendChild(el('label', { className: 'msb-field' }, [
            el('span', { text: 'Odpowiedzi do wyboru (każda w osobnej linii)' }),
            options
        ]));

        var hasOther = el('input', { type: 'checkbox' });
        hasOther.checked = !!q.other;
        var otherLabel = el('input', { type: 'text', className: 'regular-text', 'aria-label': 'Nazwa pola z własną odpowiedzią' });
        otherLabel.value = q.other || 'Inne (jakie?)';
        otherLabel.hidden = !q.other;
        hasOther.addEventListener('change', function () {
            otherLabel.hidden = !hasOther.checked;
            q.other = hasOther.checked ? (otherLabel.value.trim() || 'Inne (jakie?)') : '';
            changed();
        });
        otherLabel.addEventListener('input', function () {
            q.other = otherLabel.value.trim() || 'Inne (jakie?)';
            changed();
        });
        wrap.appendChild(el('div', { className: 'msb-inline' }, [
            el('label', {}, [hasOther, el('span', { text: ' Dodaj odpowiedź z polem do wpisania: ' })]),
            otherLabel
        ]));

        return wrap;
    }

    function lettersFields(q) {
        q.petitions = q.petitions || [];
        var wrap = el('div', { className: 'msb-letters' }, [
            el('p', { className: 'description', text: 'Zaznacz petycje, które mają być wierszami tabeli. Gdy nic nie jest zaznaczone, tabela pokazuje wszystkie opublikowane petycje. Nazwa w tabeli jest opcjonalna: puste pole = tytuł petycji i kraj.' })
        ]);

        petitions.forEach(function (petition) {
            var picked = q.petitions.filter(function (row) { return row.id === petition.id; })[0];
            var box = el('input', { type: 'checkbox' });
            box.checked = !!picked;
            var name = el('input', { type: 'text', className: 'regular-text', placeholder: petition.label, 'aria-label': 'Nazwa w tabeli: ' + petition.label });
            name.value = picked ? (picked.label || '') : '';

            function sync() {
                // Rows follow the order of the petitions list, whatever order they were ticked in
                q.petitions = petitions.filter(function (p) {
                    return p.id === petition.id ? box.checked : q.petitions.some(function (row) { return row.id === p.id; });
                }).map(function (p) {
                    var existing = q.petitions.filter(function (row) { return row.id === p.id; })[0];
                    return { id: p.id, label: p.id === petition.id ? name.value.trim() : (existing ? existing.label : '') };
                });
                changed();
            }
            box.addEventListener('change', sync);
            name.addEventListener('input', function () { if (box.checked) sync(); });

            var title = petition.label + (petition.published ? '' : ' (nieopublikowana)');
            wrap.appendChild(el('div', { className: 'msb-petition' }, [
                el('label', {}, [box, el('span', { text: ' ' + title })]),
                name
            ]));
        });

        if (!petitions.length) {
            wrap.appendChild(el('p', { text: 'Brak petycji.' }));
        }
        return wrap;
    }

    function conditionFields(q, index) {
        var questionSelect = el('select', { 'aria-label': 'Pytanie, od którego zależy widoczność' });
        var valueSelect = el('select', { 'aria-label': 'Odpowiedź, przy której pytanie się pokazuje' });
        var wrap = el('div', { className: 'msb-condition' }, [
            el('span', { text: 'Pokaż: ' }), questionSelect, el('span', { className: 'msb-condition-value-label', text: ' gdy zaznaczono ' }), valueSelect
        ]);

        function fillValues() {
            var source = questions.filter(function (other) { return q.condition && other.id === q.condition.question; })[0];
            valueSelect.textContent = '';
            var options = source && isChoice(source) ? source.options.slice() : [];
            if (q.condition && options.indexOf(q.condition.value) === -1) {
                var stale = el('option', { value: q.condition.value, text: '⚠ ' + q.condition.value + ' (nie ma już takiej odpowiedzi)' });
                valueSelect.appendChild(stale);
            }
            options.forEach(function (value) {
                valueSelect.appendChild(el('option', { value: value, text: value }));
            });
            if (q.condition) valueSelect.value = q.condition.value;
            var show = !!q.condition;
            valueSelect.hidden = !show;
            wrap.querySelector('.msb-condition-value-label').hidden = !show;
        }

        function refresh() {
            questionSelect.textContent = '';
            questionSelect.appendChild(el('option', { value: '', text: 'zawsze' }));
            var found = false;
            questions.slice(0, index).forEach(function (other, otherIndex) {
                if (!isChoice(other)) return;
                var option = el('option', { value: other.id, text: 'tylko, gdy w pytaniu ' + (otherIndex + 1) + '. „' + shortLabel(other) + '”' });
                if (q.condition && q.condition.question === other.id) { option.selected = true; found = true; }
                questionSelect.appendChild(option);
            });
            if (q.condition && !found) {
                questionSelect.appendChild(el('option', { value: q.condition.question, text: '⚠ pytanie, od którego zależy, jest niżej albo zostało usunięte' }));
                questionSelect.value = q.condition.question;
            }
            fillValues();
        }

        questionSelect.addEventListener('change', function () {
            var source = questions.filter(function (other) { return other.id === questionSelect.value; })[0];
            q.condition = source ? { question: source.id, value: source.options[0] || '' } : null;
            changed();
            fillValues();
        });
        valueSelect.addEventListener('change', function () {
            if (q.condition) q.condition.value = valueSelect.value;
            changed();
        });

        conditionRefreshers.push(refresh);
        return wrap;
    }

    function refreshConditions() {
        conditionRefreshers.forEach(function (refresh) { refresh(); });
    }

    function move(index, step) {
        var target = index + step;
        if (target < 0 || target >= questions.length) return;
        var item = questions.splice(index, 1)[0];
        questions.splice(target, 0, item);
        changed();
        render();
        var buttons = root.querySelectorAll('.msb-card')[target].querySelectorAll('.msb-actions .button');
        var same = buttons[step < 0 ? 0 : 1];
        (same && !same.disabled ? same : buttons[step < 0 ? 1 : 0]).focus();
    }

    function removeQuestion(index) {
        var q = questions[index];
        var dependants = questions.filter(function (other) { return other.condition && other.condition.question === q.id; });
        var message = 'Usunąć „' + shortLabel(q) + '”?\n\nOdpowiedzi z wysłanych raportów zostaną zachowane.';
        if (dependants.length) {
            message += '\nPytania zależne od tego pytania (' + dependants.length + ') będą widoczne zawsze.';
        }
        if (!window.confirm(message)) return;

        dependants.forEach(function (other) { other.condition = null; });
        questions.splice(index, 1);
        changed();
        render();
    }

    form.addEventListener('submit', function () {
        document.getElementById('maraton-survey-json').value = JSON.stringify(questions);
        dirty = false;
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    render();
})();
