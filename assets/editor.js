(function (wp) {
    'use strict';
    const el = wp.element.createElement, Fragment = wp.element.Fragment;
    const { useState, useEffect } = wp.element;
    const { InspectorControls, useBlockProps, RichTextToolbarButton } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, RangeControl, Button, Notice, Spinner } = wp.components;
    const api = wp.apiFetch;
    function useRequest(path) {
        const [state, setState] = useState({ data: null, error: '', loading: false });
        useEffect(function () {
            let live = true;
            if (!path) { setState({ data: null, error: '', loading: false }); return; }
            setState({ data: null, error: '', loading: true });
            api({ path }).then(data => { if (live) setState({ data, error: '', loading: false }); }).catch(error => { if (live) setState({ data: null, error: error.message, loading: false }); });
            return () => { live = false; };
        }, [path]);
        return state;
    }
    function EventSelect({ value, onChange }) {
        const [search, setSearch] = useState(''), [query, setQuery] = useState('');
        useEffect(() => { const timer = setTimeout(() => setQuery(search), 300); return () => clearTimeout(timer); }, [search]);
        const result = useRequest('/veranstaltungswart/v1/events?search=' + encodeURIComponent(query));
        const options = [{ value: 0, label: 'Aktuelle Veranstaltung' }].concat((result.data || []).map(row => ({ value: row.id, label: row.title + ' (#' + row.id + ')' })));
        if (value && !options.some(option => option.value === value)) options.push({ value, label: 'Ausgewählte Veranstaltung #' + value });
        return el(Fragment, null, el(TextControl, { label: 'Veranstaltung suchen', value: search, onChange: setSearch }), el(SelectControl, { label: 'Veranstaltung', value, options, onChange: value => onChange(Number(value)) }), result.error && el(Notice, { status: 'error', isDismissible: false }, result.error));
    }
    function Settings({ id }) {
        const result = useRequest(id ? '/veranstaltungswart/v1/settings/' + id : null);
        const locations = useRequest(id ? '/veranstaltungswart/v1/locations' : null);
        const [values, setValues] = useState(null), [busy, setBusy] = useState(false), [notice, setNotice] = useState('');
        useEffect(() => { setValues(result.data); setNotice(''); }, [result.data, id]);
        useEffect(() => { const sync = event => { if (event.detail.id === id) setValues(event.detail.data); }; window.addEventListener('vw-settings-saved', sync); return () => window.removeEventListener('vw-settings-saved', sync); }, [id]);
        if (!id) return el('p', null, 'Bitte zuerst eine Veranstaltung wählen.');
        if (result.loading) return el(Spinner);
        if (result.error) return el('p', null, 'Die zentralen Einstellungen können nur berechtigte Veranstaltungsbearbeiter ändern.');
        if (!values) return null;
        function save() {
            setBusy(true); setNotice('');
            api({ path: '/veranstaltungswart/v1/settings/' + id, method: 'POST', data: values }).then(data => { setValues(data); window.dispatchEvent(new CustomEvent('vw-settings-saved', { detail: { id, data } })); setNotice('Gespeichert. Die Einstellungen gelten für alle Formulare dieser Veranstaltung.'); }).catch(error => setNotice(error.message)).finally(() => setBusy(false));
        }
        const controls = Object.entries(vwEditor.fields).map(([key, field]) => {
            const props = { key, label: field[0], value: values[key], onChange: value => setValues(old => Object.assign({}, old, { [key]: value })) };
            if (field[1] === 'boolean') return el(ToggleControl, Object.assign({}, props, { checked: values[key] === '1', onChange: value => props.onChange(value ? '1' : '0') }));
            if (key === 'vw_location_id') return el(SelectControl, Object.assign({}, props, { options: [{ value: 0, label: 'Kein Ort' }].concat((locations.data || []).map(row => ({ value: row.id, label: row.name }))) }));
            if (field[1] === 'date') return el(TextControl, Object.assign({}, props, { type: 'datetime-local', value: (values[key] || '').replace(' ', 'T').slice(0, 16) }));
            if (field[1] === 'integer') return el(TextControl, Object.assign({}, props, { type: 'number', min: 0, max: key === 'vw_max_guests' ? 100 : undefined }));
            return el(key === 'vw_event_notes' ? TextareaControl : TextControl, props);
        });
        return el(Fragment, null, el('p', null, 'Diese zentralen Einstellungen werden mit dem folgenden Button sofort gespeichert, unabhängig vom Beitrag.'), controls, el(Button, { variant: 'primary', disabled: busy, onClick: save }, busy ? 'Speichert …' : 'Veranstaltungseinstellungen speichern'), notice && el(Notice, { isDismissible: false, status: 'info' }, notice));
    }
    const common = { eventId: { type: 'integer', default: 0 } };
    function edit(kind) {
        return function Edit({ attributes: a, setAttributes, context }) {
            const currentId = wp.data.useSelect(select => select('core/editor') ? select('core/editor').getCurrentPostId() : 0, []);
            const id = a.eventId || (context && context.postId) || currentId;
            const preview = useRequest(kind !== 'event-list' && id ? '/veranstaltungswart/v1/preview/' + id : null);
            const categories = useRequest(kind === 'event-list' ? '/wp/v2/vw_event_category?per_page=100' : null);
            const control = (Component, name, label, extra) => el(Component, Object.assign({ key: name, label, value: a[name], checked: a[name], onChange: value => setAttributes({ [name]: value }) }, extra || {}));
            const options = [];
            if (kind !== 'event-list') options.push(el(EventSelect, { key: 'event', value: a.eventId, onChange: eventId => setAttributes({ eventId }) }));
            if (kind === 'registration') options.push(control(TextControl, 'heading', 'Überschrift'), control(TextControl, 'button', 'Buttontext'), control(ToggleControl, 'showPlaces', 'Freie Plätze anzeigen'));
            else {
                options.push(control(ToggleControl, 'showImage', 'Beitragsbild anzeigen'), control(ToggleControl, 'showLocation', 'Ort anzeigen'));
                if (kind === 'event-card') options.push(control(TextControl, 'button', 'Buttontext'));
                if (kind === 'event-list') options.push(control(RangeControl, 'count', 'Anzahl', { min: 1, max: 50 }), control(RangeControl, 'columns', 'Spalten', { min: 1, max: 3 }), control(SelectControl, 'category', 'Kategorie', { options: [{ value: 0, label: 'Alle Kategorien' }].concat((categories.data || []).map(row => ({ value: row.id, label: row.name }))), onChange: value => setAttributes({ category: Number(value) }) }), control(ToggleControl, 'showPast', 'Vergangene Veranstaltungen einschließen'), control(ToggleControl, 'showCanceled', 'Abgesagte Veranstaltungen anzeigen'), control(TextControl, 'emptyText', 'Text bei leerer Liste (leer = ausblenden)'));
            }
            const p = preview.data;
            return el('div', useBlockProps(), el(InspectorControls, null, el(PanelBody, { title: 'Block-Einstellungen' }, options), kind === 'registration' && el(PanelBody, { title: 'Zentrale Veranstaltungseinstellungen', initialOpen: false }, el(Settings, { key: id, id }))), el('div', { className: 'vw-editor-preview' }, el('strong', null, kind === 'registration' ? a.heading || 'Anmeldung' : (kind === 'event-card' ? 'Veranstaltungskarte' : 'Veranstaltungsliste')), p && el('p', null, p.event_name + ' · ' + p.event_datum + ' · ' + p.event_zeit), preview.error && el('p', null, 'Bitte eine Veranstaltung in den Block-Einstellungen auswählen.'), kind === 'registration' && el('p', null, 'Formular mit Name, E-Mail und den zentral eingestellten Zusatzfeldern. Die Vorschau nimmt keine Anmeldungen entgegen.'), kind === 'event-list' && el('p', null, 'Dynamische Liste: ' + a.count + ' Veranstaltungen, ' + a.columns + ' Spalten. Die Website zeigt die aktuellen Termine.')));
        };
    }
    const definitions = {
        registration: { title: 'VeranstaltungsWart – Anmeldung', icon: 'forms', attributes: { heading: { type: 'string', default: 'Anmeldung' }, button: { type: 'string', default: 'Jetzt anmelden' }, showPlaces: { type: 'boolean', default: true } } },
        'event-card': { title: 'VeranstaltungsWart – Veranstaltungskarte', icon: 'calendar-alt', attributes: { showImage: { type: 'boolean', default: true }, showLocation: { type: 'boolean', default: true }, button: { type: 'string', default: 'Zur Veranstaltung' } } },
        'event-list': { title: 'VeranstaltungsWart – Veranstaltungsliste', icon: 'calendar', attributes: { count: { type: 'integer', default: 6 }, columns: { type: 'integer', default: 3 }, category: { type: 'integer', default: 0 }, showPast: { type: 'boolean', default: false }, showCanceled: { type: 'boolean', default: true }, emptyText: { type: 'string', default: '' }, showImage: { type: 'boolean', default: true }, showLocation: { type: 'boolean', default: true } } }
    };
    Object.entries(definitions).forEach(([name, definition]) => wp.blocks.registerBlockType('veranstaltungswart/' + name, Object.assign({}, definition, { apiVersion: 2, category: 'widgets', attributes: Object.assign({}, common, definition.attributes), usesContext: ['postId'], supports: { html: false, align: ['wide', 'full'], color: { text: true, background: true }, spacing: { padding: true, margin: true } }, edit: edit(name), save: () => null })));
    wp.plugins.registerPlugin('veranstaltungswart-settings', { render: function () {
        const post = wp.data.useSelect(select => ({ id: select('core/editor').getCurrentPostId(), type: select('core/editor').getCurrentPostType() }), []);
        if (post.type !== 'vw_event') return null;
        return el(wp.editPost.PluginDocumentSettingPanel, { name: 'vw-event-settings', title: 'VeranstaltungsWart', icon: 'calendar-alt' }, el(Settings, { id: post.id }));
    } });
    const tokens = { event_datum: 'Datum', event_wochentag: 'Wochentag', event_zeit: 'Beginn', event_ende: 'Ende', event_ort: 'Ort', event_adresse: 'Adresse', event_name: 'Name', event_hinweis: 'Hinweise' };
    wp.richText.registerFormatType('veranstaltungswart/data', {
        title: 'Veranstaltungsdaten einfügen', tagName: 'span', className: 'vw-event-token',
        edit: function ({ value, onChange }) {
            const type = wp.data.useSelect(select => select('core/editor').getCurrentPostType(), []);
            if (type !== 'vw_event') return null;
            return el(wp.blockEditor.BlockControls, { group: 'inline' }, el(wp.components.ToolbarGroup, null, el(wp.components.DropdownMenu, { icon: 'calendar-alt', label: 'Veranstaltungsdaten einfügen', controls: Object.entries(tokens).map(([key, title]) => ({ title, onClick: () => onChange(wp.richText.insert(value, '{' + key + '}')) })) })));
        }
    });
})(window.wp);
