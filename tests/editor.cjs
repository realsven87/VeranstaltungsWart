const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
    fs.mkdirSync('/tmp/vw-screenshots', { recursive: true });
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const { id } = JSON.parse(fs.readFileSync('/tmp/vw-http-fixture.json', 'utf8'));
    await page.goto('http://127.0.0.1:8080/wp-login.php');
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('test-only-password');
    await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
    await page.goto('http://127.0.0.1:8080/wp-admin/post.php?post=' + id + '&action=edit');
    await page.waitForFunction(() => window.wp && wp.blocks && wp.blocks.getBlockType('veranstaltungswart/registration'));
    await page.evaluate(() => {
        if (wp.data.select('core/edit-post').isFeatureActive('welcomeGuide')) wp.data.dispatch('core/edit-post').toggleFeature('welcomeGuide');
        wp.data.dispatch('core/block-editor').resetBlocks([
            wp.blocks.createBlock('core/paragraph', { content: 'Termin: {event_datum}' }),
            wp.blocks.createBlock('veranstaltungswart/registration', { heading: 'Test-Anmeldung' }),
            wp.blocks.createBlock('veranstaltungswart/event-card'),
            wp.blocks.createBlock('veranstaltungswart/event-list', { count: 3 })
        ]);
        const block = wp.data.select('core/block-editor').getBlocks()[1];
        wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
    });
    await page.getByRole('button', { name: 'Zentrale Veranstaltungseinstellungen', exact: true }).click();
    await page.getByRole('button', { name: 'Veranstaltungseinstellungen speichern', exact: true }).waitFor();
    // Selecting a location fills capacity, including zero; editing capacity then wins.
    const location = page.getByLabel('Veranstaltungsort', { exact: true });
    const capacity = page.getByLabel('Maximale Teilnehmerzahl (0 = unbegrenzt)', { exact: true });
    await location.selectOption({ label: 'Geheimer Ort' });
    if (await capacity.inputValue() !== '2') throw new Error('Location capacity not inherited');
    await location.selectOption({ label: 'Unlimited test location' });
    if (await capacity.inputValue() !== '0') throw new Error('Unlimited capacity not inherited');
    await location.selectOption({ label: 'Geheimer Ort' });
    await capacity.fill('7');
    await page.setViewportSize({ width: 1440, height: 1100 });
    await page.screenshot({ path: '/tmp/vw-screenshots/screenshot-2.png', fullPage: true });
    const gaps = await page.locator('.vw-settings-fields:visible').first().evaluate(container => {
        const fields = [...container.querySelectorAll(':scope > .vw-setting-field')];
        return fields.slice(1).map((field, index) => field.getBoundingClientRect().top - fields[index].getBoundingClientRect().bottom);
    });
    if (!gaps.length || gaps.some(gap => gap < 18)) throw new Error('Settings field spacing too small: ' + gaps);
    // Wait for a real settings response and save via the actual block UI.
    await page.getByLabel('Freitextfeld ist Pflichtfeld', { exact: true }).uncheck();
    const responsePromise = page.waitForResponse(response => decodeURIComponent(response.url()).includes('/veranstaltungswart/v1/settings/') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Veranstaltungseinstellungen speichern', exact: true }).click();
    const response = await responsePromise;
    const saved = await response.json();
    if (!response.ok() || saved.vw_message_required !== '0' || Number(saved.vw_max_capacity) !== 7) throw new Error('Editor settings save failed');
    await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
    await page.reload();
    await page.waitForFunction(() => window.wp && wp.data.select('core/block-editor').getBlocks().some(block => block.name === 'veranstaltungswart/registration'));
    const blocks = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map(block => ({ name: block.name, valid: block.isValid })));
    if (blocks.length !== 4 || blocks.some(block => !block.valid)) throw new Error('Blocks did not round-trip: ' + JSON.stringify(blocks));
    if (errors.length) throw new Error('Editor JS errors: ' + errors.join('\n'));
    // Actual front-end blocks: desktop image left, mobile image above, no overflow.
    await page.goto('http://127.0.0.1:8080/?p=' + id);
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        const geometry = await page.locator('.vw-event-card:has(.vw-card-image)').evaluateAll(cards => cards.map(card => {
            const image = card.querySelector('.vw-card-image').getBoundingClientRect();
            const body = card.querySelector('.vw-card-body').getBoundingClientRect();
            return { image: { x: image.x, y: image.y, right: image.right, bottom: image.bottom }, body: { x: body.x, y: body.y }, overflow: card.scrollWidth > card.clientWidth + 2 };
        }));
        if (geometry.length < 2) throw new Error('Missing image cards in card/list blocks');
        for (const item of geometry) {
            if (item.overflow) throw new Error('Card content overflows at ' + width);
            if (width > 700 && (item.image.right > item.body.x + 2 || Math.abs(item.image.y - item.body.y) > 2)) throw new Error('Desktop image is not left of body');
            if (width <= 700 && (item.image.bottom > item.body.y + 2 || Math.abs(item.image.x - item.body.x) > 2)) throw new Error('Mobile image is not above body');
        }
    }
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.locator('.vw-registration-container').first().screenshot({ path: '/tmp/vw-screenshots/screenshot-3.png' });
    // Native lazy auto-sizes uses size containment. Keep real raster srcset and
    // HTML dimensions: clearing sizes (as in the SVG tests) hides this regression.
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        const results = await page.locator('.vw-card-image img').evaluateAll(async images => {
            return Promise.all(images.map(async img => {
                if (!img.srcset || !img.width || !img.height) throw new Error('Missing responsive raster fixture');
                img.loading = 'lazy';
                if (!img.sizes.startsWith('auto')) img.sizes = 'auto, ' + img.sizes;
                img.scrollIntoView();
                await img.decode();
                const measure = () => {
                    const rect = img.getBoundingClientRect();
                    const wrapper = img.closest('.vw-card-image').getBoundingClientRect();
                    const card = img.closest('.vw-event-card').getBoundingClientRect();
                    const body = img.closest('.vw-event-card').querySelector('.vw-card-body').getBoundingClientRect();
                    return { imageError: Math.abs(rect.height - rect.width * 800 / 1200), wrapperError: Math.abs(wrapper.height - rect.height), cardError: Math.abs(card.height - (innerWidth > 700 ? Math.max(rect.height, body.height) : rect.height + body.height) - 2) };
                };
                // The previous rule must reproduce the bug, then the fix must recover.
                img.style.aspectRatio = 'auto';
                const broken = measure();
                img.style.removeProperty('aspect-ratio');
                const fixed = measure();
                return { broken, fixed };
            }));
        });
        if (results.length < 2) throw new Error('Missing native image cases in both blocks');
        for (const result of results) {
            if (result.broken.imageError < 10) throw new Error('Old auto-sizes bug was not reproduced: ' + JSON.stringify(result));
            if (Object.values(result.fixed).some(error => error > 3)) throw new Error('Auto-sizes leaves artificial image/card height: ' + JSON.stringify(result));
        }
        console.log('PASS: reproduced old auto-sizes height bug and verified fix at ' + width);
    }
    // Reproduce theme overrides and optimization plugins wrapping thumbnails in picture.
    await page.addStyleTag({ content: '.entry-content article { display:flex; flex-direction:column-reverse; min-height:1000px; } .entry-content .vw-card-image img { position:absolute; height:100%; object-fit:cover; }' });
    await page.locator('.vw-card-image > img').evaluateAll(images => images.forEach(img => {
        const picture = document.createElement('picture');
        img.replaceWith(picture);
        picture.appendChild(img);
    }));
    // Portrait, landscape and square images must retain their intrinsic ratio in both blocks.
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const [w, h] of [[400, 800], [800, 400], [600, 600]]) {
            const dimensions = await page.locator('.vw-card-image img').evaluateAll(async (images, size) => {
                return Promise.all(images.map(async img => {
                    img.removeAttribute('srcset');
                    img.removeAttribute('sizes');
                    img.src = 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${size.w}" height="${size.h}"><rect width="100%" height="100%" fill="blue"/></svg>`);
                    await img.decode();
                    const rect = img.getBoundingClientRect();
                    const wrapper = img.closest('.vw-card-image').getBoundingClientRect();
                    const card = img.closest('.vw-event-card').getBoundingClientRect();
                    const body = img.closest('.vw-event-card').querySelector('.vw-card-body').getBoundingClientRect();
                    const desktop = window.innerWidth > 700;
                    const expectedHeight = desktop ? Math.max(wrapper.height, body.height) : wrapper.height + body.height;
                    if (Math.abs(card.height - expectedHeight - 2) > 3) throw new Error('Card has artificial empty height');
                    if (desktop ? (wrapper.right > body.x + 2 || Math.abs(wrapper.y - body.y) > 2) : (wrapper.bottom > body.y + 2)) throw new Error('Theme moved image out of position');
                    return { width: rect.width, height: rect.height, naturalWidth: img.naturalWidth, naturalHeight: img.naturalHeight, clipped: rect.bottom > wrapper.bottom + 1 || rect.right > wrapper.right + 1 };
                }));
            }, { w, h });
            if (dimensions.length < 2) throw new Error('Missing card/list images');
            for (const item of dimensions) {
                if (item.width <= 0 || Math.abs(item.height - item.width * item.naturalHeight / item.naturalWidth) > 1 || item.clipped) throw new Error('Image cropped or distorted at ' + width + ': ' + JSON.stringify(item));
            }
        }
    }
    // Exercise actual authenticated CSV uploads, including invalid extension and invalid email rows.
    await page.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=vw_persons');
    const importNonce = await page.locator('[name="vw_import_nonce"]').inputValue();
    const upload = async (name, text) => page.request.post('http://127.0.0.1:8080/wp-admin/admin-post.php', {
        multipart: { action: 'vw_import_persons', vw_import_nonce: importNonce, csv_file: { name, mimeType: 'text/csv', buffer: Buffer.from(text) } }
    });
    const invalid = await upload('contacts.php', 'Demo;Person;demo@example.org');
    if (invalid.status() !== 500 || !(await invalid.text()).includes('gültige CSV-Datei')) throw new Error('Invalid upload extension accepted');
    const imported = await upload('contacts.csv', 'Import;Example;import@example.org\nBad;Email;not-an-email');
    if (!imported.ok() || !imported.url().includes('count=1')) throw new Error('CSV valid-row import failed');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=vw_dashboard');
    await page.locator('#wpbody-content').screenshot({ path: '/tmp/vw-screenshots/screenshot-1.png' });
    await browser.close();
    console.log('PASS: Gutenberg registers, edits settings and saves/reloads all three blocks');
})().catch(error => { console.error(error); process.exit(1); });
