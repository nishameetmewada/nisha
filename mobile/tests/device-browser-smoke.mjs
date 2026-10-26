/* Device fallback integration. Loopback, fake microphone, no account or paid API. */
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { spawn } from 'node:child_process';
import net from 'node:net';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
let server, browser, checks = 0;
const ok = (value, message) => { assert(value, message); ++checks; console.log('PASS', message); };

try {
  const probe = net.createServer();
  await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
  const port = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  const origin = `http://127.0.0.1:${port}`;
  server = spawn(process.execPath, ['node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', String(port), '--strictPort'], { cwd: root, stdio: ['ignore', 'ignore', 'pipe'] });
  let serverErrors = ''; server.stderr.on('data', data => { serverErrors += data; });
  let ready = false;
  for (let i = 0; i < 60; i++) {
    try { if ((await fetch(`${origin}/src/device.ts`)).ok) { ready = true; break; } } catch {}
    await new Promise(resolve => setTimeout(resolve, 50));
  }
  assert(ready, 'The isolated Vite server must start.');
  browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox', '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--autoplay-policy=no-user-gesture-required'] });
  const context = await browser.newContext({ permissions: ['microphone'] });
  const external = [], errors = [];
  await context.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.origin !== origin) { external.push(url.origin); return route.abort(); }
    if (url.pathname === '/__device-harness') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><body><h1>Local device test</h1></body></html>' });
    return route.continue();
  });
  const page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
  async function initialise() {
    await page.goto(`${origin}/__device-harness`);
    await page.evaluate(async () => {
      window.device = await import('/src/device.ts');
      window.streams = []; window.microphoneRequests = 0;
      window.originalMicrophone = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
      window.normalMicrophone = async constraints => {
        ++window.microphoneRequests;
        const stream = await window.originalMicrophone(constraints); window.streams.push(stream); return stream;
      };
      navigator.mediaDevices.getUserMedia = window.normalMicrophone;
    });
  }
  await initialise();
  ok(await page.evaluate(() => window.device.capabilities().recording && !window.device.capabilities().native && window.microphoneRequests === 0), 'Importing the app does not ask for microphone access.');
  const fixtureToken = 'T'.repeat(43);
  await page.evaluate(token => window.device.saveToken(token), fixtureToken);
  ok(await page.evaluate(token => window.device.readToken().then(saved => saved === token && !Object.values(localStorage).includes(token) && !Object.values(sessionStorage).includes(token)), fixtureToken), 'Browser-preview tokens stay in memory, outside persistent browser storage.');
  await page.reload(); await initialise();
  ok(await page.evaluate(() => window.device.readToken().then(value => value === null)), 'Reloading the browser preview discards its account token.');
  await page.evaluate(() => window.device.startRecording());
  const duplicate = await page.evaluate(() => window.device.startRecording().then(() => '', error => error.message));
  ok(/already in progress/.test(duplicate) && await page.evaluate(() => window.microphoneRequests === 1), 'A second recording cannot open a parallel microphone stream.');
  await page.waitForTimeout(260);
  const recording = await page.evaluate(() => window.device.stopRecording());
  const wav = Buffer.from(recording.base64, 'base64');
  ok(recording.mime === 'audio/wav' && wav.toString('ascii', 0, 4) === 'RIFF' && wav.toString('ascii', 8, 12) === 'WAVE', 'Stopping an explicit recording produces an actual WAV response.');
  ok(wav.readUInt16LE(20) === 1 && wav.readUInt16LE(22) === 1 && wav.readUInt32LE(24) === 16000 && wav.readUInt16LE(34) === 16, 'The WAV uses supported PCM, mono, 16 kHz and 16-bit samples.');
  ok(wav.readUInt32LE(40) === wav.length - 44 && recording.duration_seconds > 0 && recording.duration_seconds < 2, 'WAV data size and recorded duration agree.');
  ok(await page.evaluate(() => window.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended'))), 'Finishing capture stops every microphone track.');
  await page.evaluate(() => window.device.startRecording());
  await page.evaluate(() => window.device.clearToken());
  ok(await page.evaluate(() => window.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended'))), 'Clearing account access also discards active browser microphone capture.');
  const cleared = await page.evaluate(() => window.device.stopRecording().then(() => '', error => error.message));
  ok(/no recording/i.test(cleared), 'Discarded recordings cannot be recovered through the stop action.');

  await page.evaluate(() => {
    navigator.mediaDevices.getUserMedia = () => new Promise(resolve => { window.releaseMicrophone = async () => resolve(await window.normalMicrophone({ audio: true })); });
    window.pendingStart = window.device.startRecording().then(() => '', error => error.message);
  });
  await page.evaluate(() => window.device.cancelRecording());
  await page.evaluate(() => window.releaseMicrophone());
  ok(/cancelled/i.test(await page.evaluate(() => window.pendingStart)), 'Cancelling during a pending permission request prevents late recording startup.');
  ok(await page.evaluate(() => window.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended'))), 'A stream arriving after cancellation is immediately closed.');
  await page.evaluate(() => {
    navigator.mediaDevices.getUserMedia = async () => { throw new DOMException('Permission denied', 'NotAllowedError'); };
  });
  ok(/permission denied/i.test(await page.evaluate(() => window.device.startRecording().then(() => '', error => error.message))), 'Permission denial remains a recoverable recording error.');

  // Supply an oversized input frame to the actual recorder callback. This tests
  // its bound without waiting five minutes or requesting a real microphone.
  await page.evaluate(() => {
    navigator.mediaDevices.getUserMedia = window.normalMicrophone;
    window.originalAudioContext = window.AudioContext;
    window.AudioContext = class {
      sampleRate = 16000; state = 'running'; destination = {};
      async resume() {} async close() { this.state = 'closed'; }
      createMediaStreamSource() { return { connect() {}, disconnect() {} }; }
      createScriptProcessor() { window.testProcessor = { connect() {}, disconnect() {}, onaudioprocess: null }; return window.testProcessor; }
      createGain() { return { gain: { value: 0 }, connect() {}, disconnect() {} }; }
    };
  });
  await page.evaluate(() => window.device.startRecording());
  const boundary = await page.evaluate(async () => {
    const handler = window.testProcessor.onaudioprocess;
    handler({ inputBuffer: { getChannelData: () => new Float32Array(300 * 16000 + 8000) } });
    handler({ inputBuffer: { getChannelData: () => new Float32Array(8000) } });
    const result = await window.device.stopRecording();
    const raw = atob(result.base64);
    return { duration: result.duration_seconds, bytes: raw.length, streamsEnded: window.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended')) };
  });
  ok(boundary.duration === 300 && boundary.bytes === 44 + 300 * 16000 * 2 && boundary.streamsEnded, 'Oversized input is capped at five minutes and stops capture at the bound.');
  ok(external.length === 0 && errors.length === 0 && !/ERROR|Unhandled/.test(serverErrors), 'Device checks make no uploads, external requests or uncaught browser errors.');
  console.log(`PASS: ${checks} device browser checks. Fake local audio; no native-hardware claim.`);
} catch (error) {
  console.error(error.stack); process.exitCode = 1;
} finally {
  if (browser) await browser.close();
  if (server) { server.kill(); await new Promise(resolve => { server.once('exit', resolve); setTimeout(resolve, 1000); }); }
}
