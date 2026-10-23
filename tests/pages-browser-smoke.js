/* All-page checks against loopback only. No external services or paid AI calls. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
const origin = new URL(base).origin;
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Run page checks against local development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const email = `pages-browser-${crypto.randomUUID()}@example.test`;
const password = crypto.randomBytes(20).toString('hex');
const publicPages = ['index.php', 'practice.php', 'exams.php', 'quick-practice.php', 'login.php', 'register.php'];
const privatePages = ['dashboard.php', 'ai-reviews.php', 'logout.php'];
const errors = [], failures = [], external = [], links = new Set(), assets = new Set();
let browser, context, page, checks = 0;
const ok = (condition, message) => { assert(condition, message); checks++; console.log('PASS', message); };
const catalog = JSON.parse(execFileSync(php, ['-r', `
require 'includes/learning-catalog.php';
$sessions=[];
foreach(nisha_learning_catalog() as $course){
    $sessions[]=$course['full_exam_id'];
    foreach($course['modules'] as $module) $sessions[]=$module['practice_id'];
    foreach($course['sections'] as $section) $sessions[]=$section['exam_id'];
}
echo json_encode(array_values(array_unique($sessions)),JSON_THROW_ON_ERROR);
`], { cwd: root, encoding: 'utf8' }));

async function inspectPage(route, theme, width) {
    await page.setViewportSize({ width, height: 950 });
    await page.evaluate(value => localStorage.setItem('nisha:theme', value), theme);
    const response = await page.goto(`${base}/${route}`);
    assert.equal(response.status(), 200, `${route} should load`);
    assert.equal(new URL(page.url()).pathname, `/${route}`, `${route} should remain on the requested page`);
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await page.locator('html').getAttribute('data-theme'), theme, `${route} theme`);
    assert(await page.title().then(title => title.endsWith('— NISHA')), `${route} needs the NISHA title`);
    assert.equal(await page.locator('main#main').count(), 1, `${route} needs its main landmark`);
    assert.equal(await page.locator('main h1:visible').count(), 1, `${route} needs one visible main heading`);
    const shape = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        brokenImages: [...document.images].filter(image => !image.complete || !image.naturalWidth).map(image => image.getAttribute('src')),
        phpErrors: /(?:Fatal error|Warning:|Notice:|Parse error):?\s*(?:<|[A-Za-z_\\])/i.test(document.body.innerText),
        smallInputs: [...document.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),textarea,select')]
            .filter(input => input.getBoundingClientRect().height > 0 && parseFloat(getComputedStyle(input).fontSize) < 16).length,
    }));
    assert(!shape.overflow, `${route} must fit ${width}px`);
    assert.deepEqual(shape.brokenImages, [], `${route} must load images`);
    assert(!shape.phpErrors, `${route} must not expose PHP errors`);
    assert.equal(shape.smallInputs, 0, `${route} inputs should remain readable`);
    for (const href of await page.locator('a[href]').evaluateAll(elements => elements.map(element => element.href))) {
        const url = new URL(href);
        if (url.origin === origin) links.add(url.href);
    }
    ok(true, `${route} loads in ${theme} at ${width}px with readable controls and no overflow`);
}

(async () => {
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext();
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (['http:', 'https:'].includes(url.protocol) && url.origin !== origin) {
            external.push(url.origin); return route.abort();
        }
        return route.continue();
    });
    page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.status() >= 400) failures.push(`${response.status()} ${new URL(response.url()).pathname}`);
        if (['stylesheet', 'script', 'image', 'font'].includes(response.request().resourceType())) assets.add(response.url());
    });
    await page.goto(`${base}/index.php`);
    for (const theme of ['light', 'dark']) for (const width of [320, 390, 768, 1440]) for (const route of publicPages) {
        await inspectPage(route, theme, width);
    }
    for (const route of privatePages) {
        await page.goto(`${base}/${route}`);
        ok(new URL(page.url()).pathname === '/login.php', `${route} redirects guests to sign in`);
    }

    await page.goto(`${base}/index.php`);
    await page.setViewportSize({ width: 390, height: 844 });
    const menu = page.locator('.menu-toggle');
    await menu.click();
    ok(await menu.getAttribute('aria-expanded') === 'true' && await page.locator('#main-nav').isVisible(), 'Phone navigation opens its links');
    await page.keyboard.press('Escape');
    ok(await menu.getAttribute('aria-expanded') === 'false' && await menu.getAttribute('aria-label') === 'Open navigation' && !(await page.locator('#main-nav').isVisible()), 'Escape closes phone navigation and restores its accessible label');
    await menu.click();
    await page.locator('#main-nav a[href="index.php#how-it-works"]').click();
    ok(await menu.getAttribute('aria-expanded') === 'false' && await menu.getAttribute('aria-label') === 'Open navigation', 'Selecting a phone navigation link closes it with the correct label');
    const beforeTheme = await page.locator('html').getAttribute('data-theme');
    await page.locator('.theme-toggle').focus();
    await page.keyboard.press('Space');
    const switchedTheme = await page.locator('html').getAttribute('data-theme');
    assert.notEqual(switchedTheme, beforeTheme);
    // Another page must receive the student's persisted preference as well.
    const persistedPage = await context.newPage();
    await persistedPage.goto(`${base}/practice.php`);
    ok(await persistedPage.locator('html').getAttribute('data-theme') === switchedTheme, 'Keyboard theme control persists the selected appearance across pages');
    await persistedPage.close();

    for (const [family, course] of [['ielts', 'ielts-academic'], ['pte', 'pte-academic']]) {
        await page.goto(`${base}/practice.php?exam=${family}&skill=reading`);
        const active = await page.locator('#learning-course-tabs a[aria-current="page"]').getAttribute('data-course-id');
        const skills = await page.locator('.learning-module:visible').evaluateAll(elements => elements.map(element => element.dataset.skill));
        ok(active === course && skills.length > 0 && skills.every(skill => skill === 'reading'), `Existing ${family.toUpperCase()} practice links preserve the course and skill`);
    }

    const brokenLinks = [];
    for (const href of links) {
        const url = new URL(href);
        // Sign-in-only links are checked below in their own session. Launch links
        // are exhaustively checked against the canonical catalogue separately.
        if (/\/(?:dashboard|ai-reviews|logout)\.php$/.test(url.pathname) || url.searchParams.has('exam') && url.pathname.endsWith('/exams.php')) continue;
        const response = await context.request.get(url.href);
        if (response.status() !== 200) { brokenLinks.push(`${response.status()} ${url.pathname}${url.search}`); continue; }
        if (url.hash) {
            const text = await response.text();
            const id = decodeURIComponent(url.hash.slice(1));
            if (!text.includes(`id="${id}"`) && !text.includes(`id='${id}'`)) brokenLinks.push(`Missing anchor ${url.pathname}${url.hash}`);
        }
    }
    ok(brokenLinks.length === 0, `All ${links.size} discovered internal page links and anchors resolve`);
    for (const id of catalog) {
        const response = await context.request.get(`${base}/exams.php?exam=${encodeURIComponent(id)}&start=1`);
        assert.equal(response.status(), 200, `Session ${id} needs a real launch page`);
        const match = (await response.text()).match(/<script id="exam-data" type="application\/json">([\s\S]*?)<\/script>/);
        assert(match, `Session ${id} must provide runner data`);
        const data = JSON.parse(match[1]);
        assert.equal(data.requestedId, id);
        assert(data.exams[id] && data.exams[id].sections.length > 0, `Session ${id} must resolve to populated public content`);
    }
    ok(catalog.length === 139, `All ${catalog.length} practice, sectional and full-mock launch URLs return their requested session`);

    await page.goto(`${base}/register.php`);
    await page.locator('#name').fill('Page Audit'); await page.locator('#email').fill(email);
    await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await Promise.all([page.waitForURL('**/dashboard.php'), page.locator('.auth-card button[type="submit"]').click()]);
    const saved = await page.evaluate(async attemptId => {
        const dataResponse = await fetch('exams.php?exam=ielts-academic-01');
        const html = await dataResponse.text();
        const document = new DOMParser().parseFromString(html, 'text/html');
        const exam = JSON.parse(document.querySelector('#exam-data').textContent).exams['ielts-academic-01'];
        const response = await fetch('api/complete-exam.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ exam_id: exam.id, exam_version: exam.version, attempt_id: attemptId, answers: {}, elapsed_seconds: 60, completed_sections: [], assisted: true }),
        });
        return { status: response.status, data: await response.json() };
    }, `pages-browser-${crypto.randomUUID()}`);
    ok(saved.status === 200 && saved.data.saved === true, 'A real saved mock populates the new student history');
    for (const theme of ['light', 'dark']) for (const width of [320, 390, 768, 1440]) for (const route of privatePages) {
        await inspectPage(route, theme, width);
    }
    await page.goto(`${base}/dashboard.php`);
    ok(await page.locator('.mock-history-table tbody tr').count() === 1 && (await page.locator('.mock-history-note').innerText()).includes('AI coaching'), 'Saved history links the result and explains its separate AI feedback');
    await page.locator('.mock-review').click();
    await page.locator('#exam-result:not([hidden])').waitFor();
    ok((await page.locator('#exam-result-name').innerText()).includes('IELTS'), 'Dashboard result link restores the owned saved result');
    await page.goto(`${base}/ai-reviews.php`);
    ok(await page.locator('.empty-state').isVisible(), 'Private AI history has a useful empty state before a review is requested');
    await page.goto(`${base}/logout.php`);
    await Promise.all([page.waitForURL('**/index.php'), page.locator('form[action="logout.php"] button').click()]);
    await page.goto(`${base}/ai-reviews.php`);
    ok(new URL(page.url()).pathname === '/login.php', 'Signing out closes access to private AI history');

    ok(assets.size >= 10 && failures.length === 0, `All ${assets.size} loaded local assets succeed with no page HTTP errors`);
    ok(errors.length === 0, 'All audited pages run without JavaScript exceptions');
    ok(external.length === 0, 'Page rendering needs no external network requests');
    console.log(`Page browser checks passed: ${checks}; ${publicPages.length + privatePages.length} pages, ${catalog.length} session launches, ${assets.size} assets`);
})().catch(error => { console.error(error.stack); if (failures.length) console.error('Failed requests:', failures); if (errors.length) console.error('JavaScript errors:', errors); process.exitCode = 1; }).finally(async () => {
    if (context) await context.close();
    if (browser) await browser.close();
    try {
        execFileSync(php, ['-r', 'require "includes/bootstrap.php"; $statement=db()->prepare("DELETE FROM users WHERE email = ?"); $statement->execute([$argv[1]]);', email], { cwd: root, stdio: 'pipe' });
    } catch (_) { console.error('Page test account cleanup failed.'); process.exitCode = 1; }
});
