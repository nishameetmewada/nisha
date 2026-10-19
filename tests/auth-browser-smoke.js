/* Loopback-only auth regression: real inputs, real local database, no paid API. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync, spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const scratch = fs.mkdtempSync('/tmp/nisha-auth-browser-');
fs.chmodSync(scratch, 0o700);
const email = `auth-browser-${crypto.randomUUID()}@example.test`;
const password = crypto.randomBytes(20).toString('hex');
const servers = [];
let browser, context, checks = 0;
const errors = [];
const ok = (value, label) => { assert(value, label); ++checks; console.log('PASS', label); };

async function startServer(directory) {
    const port = await new Promise((resolve, reject) => {
        const listener = net.createServer(); listener.once('error', reject);
        listener.listen(0, '127.0.0.1', () => { const value = listener.address().port; listener.close(() => resolve(value)); });
    });
    const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', directory], { cwd: directory, stdio: ['ignore', 'ignore', 'ignore'] });
    servers.push(server);
    const base = `http://127.0.0.1:${port}`;
    for (let count = 0; count < 40; ++count) {
        try { await fetch(`${base}/login.php`); return base; }
        catch (_) { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    throw new Error('Local PHP auth test server failed to start.');
}

async function inspectFields(page, route, theme, width, unavailable = false) {
    await page.setViewportSize({ width, height: 1050 });
    await page.goto(`${route}`);
    await page.evaluate(value => localStorage.setItem('nisha:theme', value), theme);
    await page.reload();
    const wanted = route.endsWith('register.php') ? ['name', 'email', 'password', 'password_confirmation'] : ['email', 'password'];
    for (const id of wanted) {
        const input = page.locator(`#${id}`);
        assert(await input.isVisible(), `${id} must be visible`);
        assert(await input.isEditable(), `${id} must be editable`);
        const shape = await input.evaluate(element => {
            const box = element.getBoundingClientRect(), css = getComputedStyle(element);
            return { width: box.width, height: box.height, fontSize: parseFloat(css.fontSize), y: box.y, color: css.color, background: css.backgroundColor };
        });
        assert(shape.height >= 48 && shape.width >= 160 && shape.fontSize >= 16, `${id} must have a readable field size`);
        assert.notEqual(shape.color, shape.background, `${id} text must not match its field background`);
        await input.fill(id.includes('password') ? password : id === 'email' ? email : 'Auth Browser');
    }
    assert(await page.locator('.auth-card button[type="submit"]').isEnabled(), 'Submit must not be permanently disabled');
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Auth page must not overflow horizontally');
    if (width <= 680) {
        const card = await page.locator('.auth-card').boundingBox();
        const story = await page.locator('.auth-story').boundingBox();
        assert(card.y < story.y && (await page.locator(`#${wanted[0]}`).boundingBox()).y < 600, 'Mobile students must find the fields before the marketing story');
    }
    assert.equal(await page.locator('.setup-notice').count(), unavailable ? 1 : 0, 'Availability notice must match the real database state');
    ok(true, `${route.endsWith('register.php') ? 'Registration' : 'Login'} fields are visible and editable at ${width}px in ${theme}${unavailable ? ' without a database' : ''}`);
}

(async () => {
    assert.equal(execFileSync(php, ['-r', 'require "includes/bootstrap.php"; echo database_ready() ? "ready" : "unavailable";'], { cwd: root, encoding: 'utf8' }), 'ready', 'The real-auth roundtrip needs a local database with the imported schema.');
    const base = await startServer(root);
    // An isolated source copy deliberately omits private config. No live or local
    // application config is changed to exercise the unavailable-database path.
    const unavailableRoot = path.join(scratch, 'unavailable-site');
    fs.cpSync(root, unavailableRoot, { recursive: true, filter(source) {
        const relative = path.relative(root, source);
        return !relative.split(path.sep).some(part => part.startsWith('.')) && relative !== 'tests' && !relative.startsWith(`tests${path.sep}`) && relative !== path.join('config', 'config.php');
    } });
    fs.writeFileSync(path.join(unavailableRoot, 'config', 'config.php'), '<?php return ["db_name" => "", "db_user" => ""];\n', { mode: 0o600 });
    const unavailableBase = await startServer(unavailableRoot);
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext();
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (['http:', 'https:'].includes(url.protocol) && ![base, unavailableBase].includes(url.origin)) return route.abort();
        return route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    for (const theme of ['light', 'dark']) for (const width of [320, 390, 1440]) for (const route of ['login.php', 'register.php']) {
        await inspectFields(page, `${base}/${route}`, theme, width);
    }

    await page.goto(`${base}/register.php`);
    await page.locator('#name').fill('Auth Browser'); await page.locator('#email').fill(email);
    await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await Promise.all([page.waitForURL('**/dashboard.php'), page.locator('.auth-card button[type="submit"]').click()]);
    ok((await page.locator('h1').innerText()).includes('Auth'), 'Registration creates the real local account and opens its private dashboard');
    await Promise.all([page.waitForURL('**/index.php'), page.locator('form[action="logout.php"] button').click()]);
    await page.goto(`${base}/dashboard.php`);
    ok(new URL(page.url()).pathname === '/login.php', 'A signed-out student cannot open the private dashboard');
    await page.locator('#email').fill(email); await page.locator('#password').fill('incorrect-but-not-empty');
    await page.locator('.auth-card button[type="submit"]').click();
    ok(new URL(page.url()).pathname === '/login.php' && (await page.locator('.form-error').innerText()).includes('incorrect'), 'A wrong password produces a real sign-in error');
    await page.locator('#password').fill(password);
    await Promise.all([page.waitForURL('**/dashboard.php'), page.locator('.auth-card button[type="submit"]').click()]);
    ok((await page.locator('h1').innerText()).includes('Auth'), 'The created student can sign in with its password and regain its private dashboard');
    const privateContext = await browser.newContext();
    const privatePage = await privateContext.newPage();
    await privatePage.goto(`${base}/dashboard.php`);
    ok(new URL(privatePage.url()).pathname === '/login.php', 'Another browser session cannot access the authenticated student dashboard');
    await privateContext.close();

    for (const route of ['login.php', 'register.php']) {
        await inspectFields(page, `${unavailableBase}/${route}`, 'dark', 320, true);
        await page.locator('.auth-card button[type="submit"]').click();
        ok(new URL(page.url()).pathname === `/${route}` && (await page.locator('.form-error').innerText()).includes('temporarily unavailable') && await page.locator('#email').isEditable(), `${route} reports unavailable account access without claiming success or disabling the fields`);
        ok(await page.locator('#email').inputValue() === email && await page.locator('#password').inputValue() === '', `${route} preserves the email but does not echo the submitted password`);
    }
    ok(errors.length === 0, 'Authentication pages have no browser JavaScript errors');
    console.log(`Auth browser checks passed: ${checks}`);
})().catch(error => { console.error(error.stack); process.exitCode = 1; }).finally(async () => {
    if (context) await context.close();
    if (browser) await browser.close();
    for (const server of servers) server.kill('SIGTERM');
    try {
        execFileSync(php, ['-r', 'require "includes/bootstrap.php"; $statement=db()->prepare("DELETE FROM users WHERE email = ?"); $statement->execute([$argv[1]]);', email], { cwd: root, stdio: 'pipe' });
    } catch (_) { console.error('Auth test account cleanup failed.'); process.exitCode = 1; }
    fs.rmSync(scratch, { recursive: true, force: true });
});
