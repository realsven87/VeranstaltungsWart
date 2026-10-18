const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
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
    await browser.close();
    console.log('PASS: Gutenberg registers, edits settings and saves/reloads all three blocks');
})().catch(error => { console.error(error); process.exit(1); });
