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
    // Wait for a real settings response and save via the actual block UI.
    await page.getByLabel('Freitextfeld ist Pflichtfeld', { exact: true }).uncheck();
    const responsePromise = page.waitForResponse(response => response.url().includes('/veranstaltungswart/v1/settings/') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Veranstaltungseinstellungen speichern', exact: true }).click();
    const response = await responsePromise;
    if (!response.ok() || (await response.json()).vw_message_required !== '0') throw new Error('Editor settings save failed');
    await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
    await page.reload();
    await page.waitForFunction(() => window.wp && wp.data.select('core/block-editor').getBlocks().some(block => block.name === 'veranstaltungswart/registration'));
    const blocks = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map(block => ({ name: block.name, valid: block.isValid })));
    if (blocks.length !== 4 || blocks.some(block => !block.valid)) throw new Error('Blocks did not round-trip: ' + JSON.stringify(blocks));
    if (errors.length) throw new Error('Editor JS errors: ' + errors.join('\n'));
    await browser.close();
    console.log('PASS: Gutenberg registers, edits settings and saves/reloads all three blocks');
})().catch(error => { console.error(error); process.exit(1); });
