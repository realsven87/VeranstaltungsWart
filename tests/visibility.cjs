const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
    const { hidden: id } = JSON.parse(fs.readFileSync('/tmp/vw-visibility.json', 'utf8'));
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const publicPage = await browser.newPage();
    const base = 'http://127.0.0.1:8080';
    await page.goto(base + '/wp-login.php');
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('test-only-password');
    await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
    await page.goto(base + '/wp-admin/post.php?post=' + id + '&action=edit');
    await page.waitForFunction(() => window.wp && wp.blocks && wp.blocks.getBlockType('veranstaltungswart/registration'));
    await page.evaluate(() => {
        if (wp.data.select('core/edit-post').isFeatureActive('welcomeGuide')) wp.data.dispatch('core/edit-post').toggleFeature('welcomeGuide');
        wp.data.dispatch('core/block-editor').resetBlocks([wp.blocks.createBlock('veranstaltungswart/registration')]);
        wp.data.dispatch('core/block-editor').selectBlock(wp.data.select('core/block-editor').getBlocks()[0].clientId);
    });
    await page.getByRole('button', { name: 'Zentrale Veranstaltungseinstellungen', exact: true }).click();
    const checkbox = page.getByRole('checkbox', { name: 'Nur über Direktlink erreichbar', exact: true });
    if (!(await checkbox.isChecked())) throw new Error('Saved visibility missing from checkbox');
    for (const hidden of [false, true]) {
        await checkbox.setChecked(hidden);
        const saved = page.waitForResponse(r => r.request().method() === 'POST' && decodeURIComponent(r.url()).includes('/veranstaltungswart/v1/settings/'));
        await page.getByRole('button', { name: 'Veranstaltungseinstellungen speichern', exact: true }).click();
        const response = await saved;
        if (!response.ok() || (await response.json()).vw_direct_link_only !== (hidden ? '1' : '0')) throw new Error('Visibility not saved');
        await publicPage.goto(base + '/?p=' + id);
        const robots = await publicPage.locator('meta[name=robots]').getAttribute('content');
        if (robots.includes('noindex') !== hidden) throw new Error('Robots state not updated');
        await publicPage.goto(base + '/?s=VisibilityFixture');
        const visible = (await publicPage.content()).includes('VisibilityFixture hidden');
        if (visible === hidden) throw new Error('Search visibility not updated');
    }
    await browser.close();
    console.log('PASS: visibility checkbox saves and reverses public search/robots state');
})().catch(error => { console.error(error); process.exit(1); });
