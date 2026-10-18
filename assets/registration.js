(function () {
    'use strict';
    function init() {
        document.querySelectorAll('.vw-questions').forEach(container => {
            const form = container.closest('form');
            const guests = form.querySelector('[name="guests"]');
            const template = container.querySelector('template');
            const list = container.querySelector('.vw-guest-questions');
            const cache = new Map();
            function syncGuests() {
                if (!template) return;
                const count = Math.max(0, Math.min(100, Number(guests.value) || 0));
                for (const group of [...list.children]) {
                    cache.set(Number(group.dataset.guest), group);
                    group.remove();
                }
                for (let i = 1; i <= count; i++) {
                    let group = cache.get(i);
                    if (!group) {
                        group = template.content.firstElementChild.cloneNode(true);
                        group.dataset.guest = i;
                        group.querySelector('legend').textContent = container.dataset.guestLabel.replace('%d', i);
                        group.querySelectorAll('[data-vw-person]').forEach(field => { field.dataset.vwPerson = i; });
                        group.querySelectorAll('[id]').forEach(node => { node.id = node.id.replace('__guest__', i); });
                        group.querySelectorAll('[aria-describedby]').forEach(node => { node.setAttribute('aria-describedby', node.getAttribute('aria-describedby').replace('__guest__', i)); });
                        cache.set(i, group);
                    }
                    list.appendChild(group);
                }
                validateGroups();
            }
            function validateGroups() {
                container.querySelectorAll('.vw-question[data-required="1"]').forEach(group => {
                    const boxes = [...group.querySelectorAll('input[type=checkbox]')];
                    if (boxes.length) boxes[0].setCustomValidity(boxes.some(box => box.checked) ? '' : container.dataset.requiredMessage);
                });
            }
            container.addEventListener('change', validateGroups);
            guests.addEventListener('change', syncGuests);
            syncGuests();
            validateGroups();
            form.addEventListener('submit', () => {
                const answers = {};
                container.querySelectorAll('[data-vw-question]').forEach(field => {
                    const q = field.dataset.vwQuestion, person = field.dataset.vwPerson;
                    if (!answers[q]) answers[q] = {};
                    if (field.type === 'checkbox') {
                        if (!answers[q][person]) answers[q][person] = [];
                        if (field.checked) answers[q][person].push(field.value);
                    } else answers[q][person] = field.value;
                });
                form.querySelector('[name="vw_answers"]').value = JSON.stringify(answers);
            });
            form.querySelector('button[type=submit]').disabled = false;
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
