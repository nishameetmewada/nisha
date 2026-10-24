/* Local lesson-discovery fixtures. No external videos, payments or AI calls. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
const origin = new URL(base).origin;
if (!['localhost', '127.0.0.1'].includes(new URL(base).hostname)) throw new Error('Use loopback development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const suffix = crypto.randomBytes(8).toString('hex');
const marker = `LessonFilter${suffix}`;
const password = crypto.randomBytes(24).toString('hex');
const emails = ['student', 'teacher', 'rejected'].map(role => `lesson-filter-${role}-${suffix}@example.test`);
let fixture, browser, context, page, checks = 0;
const errors = [], external = [];
const ok = (value, label) => { assert(value, label); ++checks; console.log('PASS', label); };
function local(action, input = {}) {
    const source = `require 'includes/teachers.php';try {
    $v=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$p=db();$out=[];
    if($argv[1]==='init') {
        $baseline=nisha_teacher_catalog_count();$ids=[];
        foreach($v['emails'] as $email){$q=$p->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute(['Lesson discovery fixture',$email,password_hash($v['password'],PASSWORD_DEFAULT)]);$ids[]=(int)$p->lastInsertId();}
        foreach([[$ids[1],'approved'],[$ids[2],'rejected']] as [$id,$status]){$q=$p->prepare('INSERT INTO teacher_profiles(user_id,display_name,bio,status) VALUES(?,?,?,?)');$q->execute([$id,$v['marker'].' Teacher','Artificial local fixture only.',$status]);}
        $rows=[[$ids[1],'Reading 50% progress','ielts-academic','reading','published'],[$ids[1],'Writing task','ielts-academic','writing','published'],[$ids[1],'Speaking task','pte-academic','speaking','published'],[$ids[1],'Reading_under_score','pte-core','reading','published'],[$ids[1],'Pending lesson','ielts-general','listening','pending'],[$ids[2],'Rejected teacher lesson','ielts-general','reading','published']];
        foreach($rows as [$id,$title,$course,$skill,$status]){$q=$p->prepare('INSERT INTO coaching_videos(teacher_id,title,description,video_url,duration_seconds,course_id,skill,status) VALUES(?,?,?,?,120,?,?,?)');$q->execute([$id,$v['marker'].' '.$title,'Artificial lesson discovery fixture for filters only.','https://media.example.org/local-fixture.mp4',$course,$skill,$status]);}
        $out=['ids'=>$ids,'baseline'=>$baseline];
    } elseif($argv[1]==='hide') {$q=$p->prepare("UPDATE coaching_videos SET status='pending' WHERE teacher_id IN (?,?)");$q->execute([$v['ids'][1],$v['ids'][2]]);$out=['hidden'=>true];}
    elseif($argv[1]==='cleanup') {$q=$p->prepare('DELETE FROM users WHERE id IN (?,?,?) AND email IN (?,?,?)');$q->execute(array_merge($v['ids'],$v['emails']));$out=['cleaned'=>true];}
    echo json_encode($out,JSON_THROW_ON_ERROR);session_write_close();
    }catch(Throwable $e){fwrite(STDERR,'Local coaching fixture operation failed.');exit(1);}`;
    return JSON.parse(execFileSync(php, ['-r', source, action], { cwd: root, input: JSON.stringify(input), encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }));
}
async function find(values) {
    const form = page.locator('.teacher-filters');
    await form.locator('[name=q]').fill(values.q || '');
    await form.locator('[name=course]').selectOption(values.course || '');
    await form.locator('[name=skill]').selectOption(values.skill || '');
    await Promise.all([page.waitForNavigation(), form.locator('button[type=submit]').click()]);
}
(async () => {
    fixture = local('init', { emails, password, marker });
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext();
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (['http:', 'https:'].includes(url.protocol) && url.origin !== origin) { external.push(url.origin); return route.abort(); }
        return route.continue();
    });
    page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/login.php`);
    await page.locator('#email').fill(emails[0]); await page.locator('#password').fill(password);
    await Promise.all([page.waitForURL('**/dashboard.php'), page.locator('.auth-card button[type=submit]').click()]);
    await page.goto(`${base}/coaching.php`);
    await find({ q: marker });
    ok(await page.locator('.coaching-card').count() === 4 && /4 matching lessons/.test(await page.locator('#coaching-results').innerText()), 'Find lessons returns published lessons and an accurate count, excluding pending lessons and rejected teachers');
    ok(new URL(page.url()).hash === '#coaching-results' && await page.locator('[name=q]').inputValue() === marker, 'Submitting Find lessons moves to its results and preserves the search');
    ok(await page.locator('video,source').count() === 0 && /paid NISHA access pass/.test(await page.locator('.coaching-card').first().innerText()), 'Students can discover lesson metadata without fetching paid video sources');
    await find({ q: marker, course: 'ielts-academic', skill: 'reading' });
    ok(await page.locator('.coaching-card').count() === 1 && /Reading 50% progress/.test(await page.locator('.coaching-card h2').innerText()), 'Course and skill filters combine with the search through the real form');
    await find({ q: marker, course: 'pte-academic', skill: 'speaking' });
    ok(await page.locator('.coaching-card').count() === 1 && /Speaking task/.test(await page.locator('.coaching-card h2').innerText()), 'Changing Find lessons filters displays the newly selected PTE lesson');
    await find({ q: `${marker} Reading 50%` });
    ok(await page.locator('.coaching-card').count() === 1, 'A percent character in a topic is searched literally');
    await find({ q: `${marker} Reading_under` });
    ok(await page.locator('.coaching-card').count() === 1, 'An underscore character in a topic is searched literally');
    await find({ q: marker, course: 'ielts-general' });
    ok(await page.locator('.coaching-card').count() === 0 && /No lessons match your search/.test(await page.locator('#coaching-results').innerText()), 'An unmatched search explains the empty result rather than implying the button failed');
    await page.getByRole('link', { name: 'Show all lessons' }).click();
    ok(await page.locator('[name=q]').inputValue() === '' && await page.locator('[name=course]').inputValue() === '' && await page.locator('.coaching-card').count() >= 4, 'Show all lessons clears filters and restores available lessons');
    for (const mode of ['light', 'dark']) for (const width of [320, 390, 1440]) {
        await page.evaluate(value => localStorage.setItem('nisha:theme', value), mode);
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`${base}/coaching.php?q=${marker}`);
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) console.error(await page.evaluate(() => [...document.querySelectorAll('main *')].map(node => ({ tag: node.tagName, cls: node.className, width: node.getBoundingClientRect().width, right: node.getBoundingClientRect().right })).filter(node => node.right > innerWidth + 1)));
        ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Lesson search and results fit ${width}px in ${mode} mode`);
    }
    local('hide', { ids: fixture.ids });
    if (fixture.baseline === 0) {
        await page.goto(`${base}/coaching.php`);
        ok(/No coaching lessons have been published yet/.test(await page.locator('#coaching-results').innerText()), 'An empty library explicitly explains that teachers and the owner must publish lessons first');
    }
    ok(errors.length === 0 && external.length === 0, 'Lesson discovery finishes without browser errors or external media requests');
    console.log(`PASS: ${checks} coaching lesson discovery checks. Exact disposable fixtures cleaned; no production lessons added.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) await browser.close();
    if (fixture) local('cleanup', { ids: fixture.ids, emails });
});
