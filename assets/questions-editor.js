(function (wp) {
    'use strict';
    const el = wp.element.createElement;
    const { TextControl, TextareaControl, ToggleControl, SelectControl, Button } = wp.components;
    const { __, sprintf } = wp.i18n;
    const id = () => 'q_' + crypto.randomUUID();
    window.VWQuestionsEditor = function ({ value, onChange }) {
        const questions = value || [];
        const change = (index, patch) => onChange(questions.map((q, i) => i === index ? Object.assign({}, q, patch) : q));
        return el('div', { className: 'vw-questions-editor' },
            el('h3', null, __('Zusatzfragen', 'veranstaltungswart')),
            el('p', null, __('Fragen gelten für diese Veranstaltung. Deaktivierte Fragen und Optionen verschwinden aus neuen Formularen; gespeicherte Antworten bleiben erhalten. Zum Ändern von Typ oder Abfrage eine neue Frage anlegen.', 'veranstaltungswart')),
            questions.map((q, index) => el('fieldset', { key: q.id, className: 'vw-question-config' },
                el('legend', null, sprintf(__('Frage %d', 'veranstaltungswart'), index + 1)),
                el(TextControl, { label: __('Bezeichnung', 'veranstaltungswart'), value: q.label, maxLength: 200, onChange: label => change(index, { label }) }),
                el(TextareaControl, { label: __('Erklärung (optional)', 'veranstaltungswart'), value: q.hint, maxLength: 1000, onChange: hint => change(index, { hint }) }),
                el(SelectControl, { label: __('Feldtyp', 'veranstaltungswart'), disabled: !q._new, value: q.type, options: [{ value: 'single', label: __('Einzelauswahl', 'veranstaltungswart') }, { value: 'multiple', label: __('Mehrfachauswahl', 'veranstaltungswart') }, { value: 'text', label: __('Freitext', 'veranstaltungswart') }], onChange: type => change(index, { type }) }),
                el(SelectControl, { label: __('Abfrage', 'veranstaltungswart'), disabled: !q._new, value: q.scope, options: [{ value: 'person', label: __('Pro Person', 'veranstaltungswart') }, { value: 'registration', label: __('Einmal pro Anmeldung', 'veranstaltungswart') }], onChange: scope => change(index, { scope }) }),
                el(ToggleControl, { label: __('Pflichtfeld', 'veranstaltungswart'), checked: q.required, onChange: required => change(index, { required }) }),
                el(ToggleControl, { label: __('Frage aktiv', 'veranstaltungswart'), checked: q.active, onChange: active => change(index, { active }) }),
                q.type !== 'text' && el('div', null,
                    q.options.map((o, oi) => el('div', { key: o.id, className: 'vw-option-config' },
                        el(TextControl, { label: sprintf(__('Option %d', 'veranstaltungswart'), oi + 1), value: o.label, maxLength: 200, onChange: label => change(index, { options: q.options.map((v, i) => i === oi ? Object.assign({}, v, { label }) : v) }) }),
                        el(ToggleControl, { label: __('Option aktiv', 'veranstaltungswart'), checked: o.active, onChange: active => change(index, { options: q.options.map((v, i) => i === oi ? Object.assign({}, v, { active }) : v) }) })
                    )),
                    el(Button, { variant: 'secondary', disabled: q.options.length >= 30, onClick: () => change(index, { options: q.options.concat({ id: id(), label: '', active: true }) }) }, __('Option hinzufügen', 'veranstaltungswart'))
                ),
                q._new && el(Button, { variant: 'tertiary', isDestructive: true, onClick: () => onChange(questions.filter((_, i) => i !== index)) }, __('Ungespeicherte Frage entfernen', 'veranstaltungswart'))
            )),
            el(Button, { variant: 'secondary', disabled: questions.length >= 20, onClick: () => onChange(questions.concat({ id: id(), label: '', hint: '', type: 'single', scope: 'person', required: false, active: true, options: [], _new: true })) }, __('Frage hinzufügen', 'veranstaltungswart'))
        );
    };
    function mountClassic() {
        document.querySelectorAll('[data-vw-question-editor]').forEach(container => {
            const field = container.querySelector('input[type=hidden]');
            const host = container.querySelector('.vw-question-editor-root');
            function Editor() {
                const [value, setValue] = wp.element.useState(JSON.parse(field.value));
                return el(window.VWQuestionsEditor, { value, onChange: next => { field.value = JSON.stringify(next); setValue(next); } });
            }
            wp.element.createRoot(host).render(el(Editor));
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountClassic); else mountClassic();
})(window.wp);
