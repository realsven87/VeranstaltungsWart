(function (wp) {
    'use strict';
    const el = wp.element.createElement, Fragment = wp.element.Fragment;
    const { useState, useEffect } = wp.element;
    const { InspectorControls, useBlockProps, RichTextToolbarButton } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, RangeControl, Button, Notice, Spinner } = wp.components;
    const api = wp.apiFetch;
    const { __, sprintf } = wp.i18n;
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
        const options = [{ value: 0, label: __('Aktuelle Veranstaltung', 'veranstaltungswart') }].concat((result.data || []).map(row => ({ value: row.id, label: row.title + ' (#' + row.id + ')' })));
        if (value && !options.some(option => option.value === value)) options.push({ value, label: sprintf(__('Ausgewählte Veranstaltung #%d', 'veranstaltungswart'), value) });
        return el(Fragment, null, el(TextControl, { label: __('Veranstaltung suchen', 'veranstaltungswart'), value: search, onChange: setSearch }), el(SelectControl, { label: __('Veranstaltung', 'veranstaltungswart'), value, options, onChange: value => onChange(Number(value)) }), result.error && el(Notice, { status: 'error', isDismissible: false }, result.error));
    }
    function Settings({ id }) {
        const result = useRequest(id ? '/veranstaltungswart/v1/settings/' + id : null);
        const locations = useRequest(id ? '/veranstaltungswart/v1/locations' : null);
        const [values, setValues] = useState(null), [busy, setBusy] = useState(false), [notice, setNotice] = useState('');
        useEffect(() => { setValues(result.data); setNotice(''); }, [result.data, id]);
        useEffect(() => { const sync = event => { if (event.detail.id === id) setValues(event.detail.data); }; window.addEventListener('vw-settings-saved', sync); return () => window.removeEventListener('vw-settings-saved', sync); }, [id]);
        if (!id) return el('p', null, __('Bitte zuerst eine Veranstaltung wählen.', 'veranstaltungswart'));
        if (result.loading) return el(Spinner);
        if (result.error) return el('p', null, __('Die zentralen Einstellungen können nur berechtigte Veranstaltungsbearbeiter ändern.', 'veranstaltungswart'));
        if (!values) return null;
        function save() {
            setBusy(true); setNotice('');
            api({ path: '/veranstaltungswart/v1/settings/' + id, method: 'POST', data: values }).then(data => { setValues(data); window.dispatchEvent(new CustomEvent('vw-settings-saved', { detail: { id, data } })); setNotice(__('Gespeichert. Die Einstellungen gelten für alle Formulare dieser Veranstaltung.', 'veranstaltungswart')); }).catch(error => setNotice(error.message)).finally(() => setBusy(false));
        }
        const controls = Object.entries(vwEditor.fields).map(([key, field]) => {
            const props = { key, label: field[0], value: values[key], onChange: value => setValues(old => Object.assign({}, old, { [key]: value })) };
            if (field[1] === 'questions') return el(window.VWQuestionsEditor, props);
            if (field[1] === 'boolean') return el(ToggleControl, Object.assign({}, props, { checked: values[key] === '1', onChange: value => props.onChange(value ? '1' : '0') }));
            if (key === 'vw_location_id') return el(SelectControl, Object.assign({}, props, {
                disabled: locations.loading, help: __('Beim Ortswechsel wird die Kapazität übernommen. Danach kannst du sie unten anpassen.', 'veranstaltungswart'),
                options: [{ value: 0, label: __('Kein Ort', 'veranstaltungswart') }].concat((locations.data || []).map(row => ({ value: row.id, label: row.name }))),
                onChange: value => setValues(old => {
                    const location = (locations.data || []).find(row => row.id === Number(value));
                    return Object.assign({}, old, { vw_location_id: Number(value) }, location ? { vw_max_capacity: location.default_capacity } : {});
                })
            }));
            if (field[1] === 'date') return el(TextControl, Object.assign({}, props, { type: 'datetime-local', value: (values[key] || '').replace(' ', 'T').slice(0, 16) }));
            if (field[1] === 'integer') return el(TextControl, Object.assign({}, props, { type: 'number', min: 0, max: key === 'vw_max_guests' ? 100 : undefined }));
            return el(key === 'vw_event_notes' ? TextareaControl : TextControl, props);
        });
        return el(Fragment, null, el('p', null, __('Diese zentralen Einstellungen werden mit dem folgenden Button sofort gespeichert, unabhängig vom Beitrag.', 'veranstaltungswart')), el('div', { className: 'vw-settings-fields' }, controls.map((control, index) => el('div', { className: 'vw-setting-field', key: Object.keys(vwEditor.fields)[index] }, control))), el(Button, { variant: 'primary', disabled: busy, onClick: save }, busy ? __('Speichert …', 'veranstaltungswart') : __('Veranstaltungseinstellungen speichern', 'veranstaltungswart')), notice && el(Notice, { isDismissible: false, status: 'info' }, notice));
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
            if (kind === 'registration') options.push(control(TextControl, 'heading', __('Überschrift', 'veranstaltungswart')), control(TextControl, 'button', __('Buttontext', 'veranstaltungswart')), control(ToggleControl, 'showPlaces', __('Freie Plätze anzeigen', 'veranstaltungswart')));
            else {
                options.push(control(ToggleControl, 'showImage', __('Beitragsbild anzeigen', 'veranstaltungswart')), control(ToggleControl, 'showLocation', __('Ort anzeigen', 'veranstaltungswart')));
                if (kind === 'event-card') options.push(control(TextControl, 'button', __('Buttontext', 'veranstaltungswart')));
                if (kind === 'event-list') options.push(control(RangeControl, 'count', __('Anzahl', 'veranstaltungswart'), { min: 1, max: 50 }), control(RangeControl, 'columns', __('Spalten', 'veranstaltungswart'), { min: 1, max: 3 }), control(SelectControl, 'category', __('Kategorie', 'veranstaltungswart'), { options: [{ value: 0, label: __('Alle Kategorien', 'veranstaltungswart') }].concat((categories.data || []).map(row => ({ value: row.id, label: row.name }))), onChange: value => setAttributes({ category: Number(value) }) }), control(ToggleControl, 'showPast', __('Vergangene Veranstaltungen einschließen', 'veranstaltungswart')), control(ToggleControl, 'showCanceled', __('Abgesagte Veranstaltungen anzeigen', 'veranstaltungswart')), control(TextControl, 'emptyText', __('Text bei leerer Liste (leer = ausblenden)', 'veranstaltungswart')));
            }
            const p = preview.data;
            return el('div', useBlockProps(), el(InspectorControls, null, el(PanelBody, { title: __('Block-Einstellungen', 'veranstaltungswart') }, options), kind === 'registration' && el(PanelBody, { title: __('Zentrale Veranstaltungseinstellungen', 'veranstaltungswart'), initialOpen: false }, el(Settings, { key: id, id }))), el('div', { className: 'vw-editor-preview' }, el('strong', null, kind === 'registration' ? a.heading || __('Anmeldung', 'veranstaltungswart') : (kind === 'event-card' ? __('Veranstaltungskarte', 'veranstaltungswart') : __('Veranstaltungsliste', 'veranstaltungswart'))), p && el('p', null, p.event_name + ' · ' + p.event_datum + ' · ' + p.event_zeit), preview.error && el('p', null, __('Bitte eine Veranstaltung in den Block-Einstellungen auswählen.', 'veranstaltungswart')), kind === 'registration' && el('p', null, __('Formular mit Name, E-Mail und den zentral eingestellten Zusatzfeldern. Die Vorschau nimmt keine Anmeldungen entgegen.', 'veranstaltungswart')), kind === 'event-list' && el('p', null, sprintf(__('Dynamische Liste: %1$d Veranstaltungen, %2$d Spalten. Die Website zeigt die aktuellen Termine.', 'veranstaltungswart'), a.count, a.columns))));
        };
    }
    const definitions = {
        registration: { title: __('VeranstaltungsWart – Anmeldung', 'veranstaltungswart'), icon: 'forms', attributes: { heading: { type: 'string', default: __('Anmeldung', 'veranstaltungswart') }, button: { type: 'string', default: __('Jetzt anmelden', 'veranstaltungswart') }, showPlaces: { type: 'boolean', default: true } } },
        'event-card': { title: __('VeranstaltungsWart – Veranstaltungskarte', 'veranstaltungswart'), icon: 'calendar-alt', attributes: { showImage: { type: 'boolean', default: true }, showLocation: { type: 'boolean', default: true }, button: { type: 'string', default: __('Zur Veranstaltung', 'veranstaltungswart') } } },
        'event-list': { title: __('VeranstaltungsWart – Veranstaltungsliste', 'veranstaltungswart'), icon: 'calendar', attributes: { count: { type: 'integer', default: 6 }, columns: { type: 'integer', default: 3 }, category: { type: 'integer', default: 0 }, showPast: { type: 'boolean', default: false }, showCanceled: { type: 'boolean', default: true }, emptyText: { type: 'string', default: '' }, showImage: { type: 'boolean', default: true }, showLocation: { type: 'boolean', default: true } } }
    };
    Object.entries(definitions).forEach(([name, definition]) => wp.blocks.registerBlockType('veranstaltungswart/' + name, Object.assign({}, definition, { apiVersion: 2, category: 'widgets', attributes: Object.assign({}, common, definition.attributes), usesContext: ['postId'], supports: { html: false, align: ['wide', 'full'], color: { text: true, background: true }, spacing: { padding: true, margin: true } }, edit: edit(name), save: () => null })));
    wp.plugins.registerPlugin('veranstaltungswart-settings', { render: function () {
        const post = wp.data.useSelect(select => ({ id: select('core/editor').getCurrentPostId(), type: select('core/editor').getCurrentPostType() }), []);
        if (post.type !== 'vw_event') return null;
        return el(wp.editPost.PluginDocumentSettingPanel, { name: 'vw-event-settings', title: 'VeranstaltungsWart', icon: 'calendar-alt' }, el(Settings, { id: post.id }));
    } });
    const tokens = { event_datum: __('Datum', 'veranstaltungswart'), event_wochentag: __('Wochentag', 'veranstaltungswart'), event_zeit: __('Beginn', 'veranstaltungswart'), event_ende: __('Ende', 'veranstaltungswart'), event_ort: __('Ort', 'veranstaltungswart'), event_adresse: __('Adresse', 'veranstaltungswart'), event_name: __('Name', 'veranstaltungswart'), event_hinweis: __('Hinweise', 'veranstaltungswart') };
    wp.richText.registerFormatType('veranstaltungswart/data', {
        title: __('Veranstaltungsdaten einfügen', 'veranstaltungswart'), tagName: 'span', className: 'vw-event-token',
        edit: function ({ value, onChange }) {
            const type = wp.data.useSelect(select => select('core/editor').getCurrentPostType(), []);
            if (type !== 'vw_event') return null;
            return el(wp.blockEditor.BlockControls, { group: 'inline' }, el(wp.components.ToolbarGroup, null, el(wp.components.DropdownMenu, { icon: 'calendar-alt', label: __('Veranstaltungsdaten einfügen', 'veranstaltungswart'), controls: Object.entries(tokens).map(([key, title]) => ({ title, onClick: () => onChange(wp.richText.insert(value, '{' + key + '}')) })) })));
        }
    });
})(window.wp);
