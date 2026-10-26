import { Capacitor, registerPlugin } from '@capacitor/core';

export interface Recording {
  base64: string;
  mime: 'audio/wav';
  duration_seconds: number;
}

interface DeviceBridge {
  getToken(): Promise<{ token?: string }>;
  setToken(value: { token: string }): Promise<void>;
  clearToken(): Promise<void>;
  startRecording(): Promise<void>;
  stopRecording(): Promise<Recording>;
  cancelRecording(): Promise<void>;
  speak(value: { text: string; locale: string }): Promise<void>;
  stopSpeech(): Promise<void>;
}

const native = registerPlugin<DeviceBridge>('NishaDevice');
const isNative = () => Capacitor.isNativePlatform();
let browserToken: string | null = null;

export function capabilities() {
  return {
    native: isNative(),
    recording: isNative() || (typeof navigator.mediaDevices?.getUserMedia === 'function' && typeof window.AudioContext === 'function'),
    speech: isNative() || 'speechSynthesis' in window,
  };
}

export async function readToken(): Promise<string | null> {
  return isNative() ? (await native.getToken()).token || null : browserToken;
}

export async function saveToken(token: string): Promise<void> {
  if (!/^[A-Za-z0-9_-]{40,200}$/.test(token)) throw new Error('The account session is invalid. Please sign in again.');
  if (isNative()) await native.setToken({ token });
  else browserToken = token;
}

export async function clearToken(): Promise<void> {
  browserToken = null;
  if (isNative()) await native.clearToken();
  else { await cancelRecording(); await stopSpeech(); }
}

type BrowserRecorder = {
  context: AudioContext;
  stream: MediaStream;
  source: MediaStreamAudioSourceNode;
  processor: ScriptProcessorNode;
  silent: GainNode;
  samples: Float32Array[];
  count: number;
  rate: number;
  timer: number;
  stopped: boolean;
};
let recorder: BrowserRecorder | null = null;
let starting = false;
let recordingGeneration = 0;

function stopBrowserCapture(record: BrowserRecorder) {
  if (record.stopped) return;
  record.stopped = true;
  window.clearTimeout(record.timer);
  record.processor.onaudioprocess = null;
  record.source.disconnect(); record.processor.disconnect(); record.silent.disconnect();
  record.stream.getTracks().forEach(track => track.stop());
  void record.context.close();
}

export async function startRecording(): Promise<void> {
  if (isNative()) return native.startRecording();
  if (recorder || starting) throw new Error('A recording is already in progress.');
  if (!capabilities().recording) throw new Error('Recording is not available on this device. You can enter a transcript instead.');
  starting = true;
  const generation = ++recordingGeneration;
  let stream: MediaStream | undefined;
  let context: AudioContext | undefined;
  try {
    stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true } });
    if (generation !== recordingGeneration) throw new Error('Recording was cancelled.');
    context = new AudioContext();
    await context.resume();
    const source = context.createMediaStreamSource(stream);
    const processor = context.createScriptProcessor(4096, 1, 1);
    const silent = context.createGain(); silent.gain.value = 0;
    const record: BrowserRecorder = { context, stream, source, processor, silent, samples: [], count: 0, rate: context.sampleRate, timer: 0, stopped: false };
    processor.onaudioprocess = event => {
      const remaining = Math.max(0, 300 * record.rate - record.count);
      if (!remaining) { stopBrowserCapture(record); return; }
      const samples = event.inputBuffer.getChannelData(0).slice(0, remaining);
      record.samples.push(samples); record.count += samples.length;
      if (record.count >= 300 * record.rate) stopBrowserCapture(record);
    };
    source.connect(processor); processor.connect(silent); silent.connect(context.destination);
    record.timer = window.setTimeout(() => stopBrowserCapture(record), 300_000);
    recorder = record;
  } catch (error) {
    stream?.getTracks().forEach(track => track.stop());
    if (context && context.state !== 'closed') await context.close();
    throw error;
  } finally { starting = false; }
}

function wavBase64(samples: Float32Array, sampleRate: number): Recording {
  const count = Math.min(300 * 16000, Math.floor(samples.length * 16000 / sampleRate));
  if (!count) throw new Error('The recording is empty. Record a spoken response first.');
  const bytes = new Uint8Array(44 + count * 2); const view = new DataView(bytes.buffer);
  const text = (offset: number, value: string) => [...value].forEach((letter, i) => bytes[offset + i] = letter.charCodeAt(0));
  text(0, 'RIFF'); view.setUint32(4, 36 + count * 2, true); text(8, 'WAVE'); text(12, 'fmt ');
  view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true);
  view.setUint32(24, 16000, true); view.setUint32(28, 32000, true); view.setUint16(32, 2, true); view.setUint16(34, 16, true);
  text(36, 'data'); view.setUint32(40, count * 2, true);
  for (let i = 0; i < count; i++) {
    const position = i * sampleRate / 16000; const left = Math.floor(position); const fraction = position - left;
    const value = Math.max(-1, Math.min(1, samples[left] * (1 - fraction) + (samples[Math.min(left + 1, samples.length - 1)] || 0) * fraction));
    view.setInt16(44 + i * 2, Math.round(value * (value < 0 ? 32768 : 32767)), true);
  }
  let binary = '';
  for (let i = 0; i < bytes.length; i += 8192) binary += String.fromCharCode(...bytes.subarray(i, i + 8192));
  return { base64: btoa(binary), mime: 'audio/wav', duration_seconds: count / 16000 };
}

export async function stopRecording(): Promise<Recording> {
  if (isNative()) return native.stopRecording();
  const record = recorder;
  if (!record) throw new Error('There is no recording to finish.');
  recorder = null; stopBrowserCapture(record);
  const samples = new Float32Array(record.count); let offset = 0;
  record.samples.forEach(chunk => { samples.set(chunk, offset); offset += chunk.length; });
  return wavBase64(samples, record.rate);
}

export async function cancelRecording(): Promise<void> {
  if (isNative()) return native.cancelRecording();
  recordingGeneration++;
  if (recorder) { stopBrowserCapture(recorder); recorder = null; }
}

export async function speak(text: string): Promise<void> {
  if (!text.trim() || text.length > 30000) throw new Error('This listening passage is unavailable.');
  if (isNative()) return native.speak({ text, locale: 'en-AU' });
  if (!('speechSynthesis' in window)) throw new Error('Speech is unavailable. Open the transcript to practise reading instead.');
  window.speechSynthesis.cancel();
  const utterance = new SpeechSynthesisUtterance(text); utterance.lang = 'en-AU'; utterance.rate = 0.92;
  window.speechSynthesis.speak(utterance);
}

export async function stopSpeech(): Promise<void> {
  if (isNative()) await native.stopSpeech();
  else if ('speechSynthesis' in window) window.speechSynthesis.cancel();
}
