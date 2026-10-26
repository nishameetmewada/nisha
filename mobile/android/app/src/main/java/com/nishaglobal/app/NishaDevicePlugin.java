package com.nishaglobal.app;

import android.Manifest;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioFormat;
import android.media.AudioRecord;
import android.media.MediaRecorder;
import android.net.Uri;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.speech.tts.TextToSpeech;
import android.util.Base64;
import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;
import java.io.ByteArrayOutputStream;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import java.util.Locale;
import java.util.UUID;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

/** Device-only account storage and explicit, local microphone practice. */
@CapacitorPlugin(name = "NishaDevice", permissions = {
    @Permission(alias = "microphone", strings = { Manifest.permission.RECORD_AUDIO })
})
public class NishaDevicePlugin extends Plugin {
    private static final String KEY_ALIAS = "com.nishaglobal.app.session.v1";
    private static final int SAMPLE_RATE = 16000;
    private static final int MAX_SAMPLES = SAMPLE_RATE * 300;
    private final Object audioLock = new Object();
    private volatile boolean recording;
    private AudioRecord microphone;
    private Thread audioThread;
    private ByteArrayOutputStream pcm;
    private String audioError;
    private int recordingGeneration;
    private boolean permissionPending;
    private TextToSpeech speech;
    private volatile boolean speechReady;

    @Override public void load() {
        getActivity().runOnUiThread(() -> {
            speech = new TextToSpeech(getContext(), status -> {
                speechReady = status == TextToSpeech.SUCCESS;
            });
        });
    }

    private SharedPreferences preferences() {
        return getContext().getSharedPreferences("nisha_private_session", Context.MODE_PRIVATE);
    }

    private SecretKey encryptionKey() throws Exception {
        KeyStore store = KeyStore.getInstance("AndroidKeyStore"); store.load(null);
        if (store.containsAlias(KEY_ALIAS)) return ((KeyStore.SecretKeyEntry) store.getEntry(KEY_ALIAS, null)).getSecretKey();
        KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
        generator.init(new KeyGenParameterSpec.Builder(KEY_ALIAS, KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setRandomizedEncryptionRequired(true).build());
        return generator.generateKey();
    }

    @PluginMethod public void getToken(PluginCall call) {
        String stored = preferences().getString("encrypted_token", null);
        if (stored == null) { call.resolve(new JSObject()); return; }
        try {
            byte[] value = Base64.decode(stored, Base64.NO_WRAP);
            if (value.length < 29) throw new IllegalStateException();
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.DECRYPT_MODE, encryptionKey(), new GCMParameterSpec(128, value, 0, 12));
            String token = new String(cipher.doFinal(value, 12, value.length - 12), StandardCharsets.UTF_8);
            JSObject result = new JSObject(); result.put("token", token); call.resolve(result);
        } catch (Exception ignored) {
            preferences().edit().remove("encrypted_token").commit();
            call.resolve(new JSObject());
        }
    }

    @PluginMethod public void setToken(PluginCall call) {
        String token = call.getString("token", "");
        if (!token.matches("[A-Za-z0-9_-]{40,200}")) { call.reject("The account session is invalid."); return; }
        try {
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding"); cipher.init(Cipher.ENCRYPT_MODE, encryptionKey());
            byte[] encrypted = cipher.doFinal(token.getBytes(StandardCharsets.UTF_8));
            ByteArrayOutputStream output = new ByteArrayOutputStream(); output.write(cipher.getIV()); output.write(encrypted);
            if (!preferences().edit().putString("encrypted_token", Base64.encodeToString(output.toByteArray(), Base64.NO_WRAP)).commit()) throw new IllegalStateException();
            call.resolve();
        } catch (Exception ignored) { call.reject("Secure account storage is unavailable. Please sign in again."); }
    }

    @PluginMethod public void clearToken(PluginCall call) {
        cancelLocalRecording(); stopLocalSpeech();
        if (!preferences().edit().remove("encrypted_token").commit()) { call.reject("The saved account session could not be cleared."); return; }
        call.resolve();
    }

    @PluginMethod public void startRecording(PluginCall call) {
        synchronized (audioLock) {
            if (pcm != null || recording || permissionPending) { call.reject("Finish or discard the current recording first."); return; }
            permissionPending = true; recordingGeneration++;
            call.getData().put("_nativeRecordingGeneration", recordingGeneration);
        }
        if (getPermissionState("microphone") != PermissionState.GRANTED) { requestPermissionForAlias("microphone", call, "microphonePermission"); return; }
        beginRecording(call);
    }

    @PermissionCallback private void microphonePermission(PluginCall call) {
        if (getPermissionState("microphone") != PermissionState.GRANTED) {
            synchronized (audioLock) { if (call.getInt("_nativeRecordingGeneration", -1) == recordingGeneration) permissionPending = false; }
            call.reject("Microphone permission was declined. You can enter a transcript instead."); return;
        }
        beginRecording(call);
    }

    @SuppressWarnings("MissingPermission")
    private void beginRecording(PluginCall call) {
        synchronized (audioLock) {
            if (call.getInt("_nativeRecordingGeneration", -1) != recordingGeneration) { call.reject("Recording was cancelled."); return; }
            permissionPending = false;
            if (pcm != null || recording) { call.reject("Finish or discard the current recording first."); return; }
            int size = AudioRecord.getMinBufferSize(SAMPLE_RATE, AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT);
            if (size <= 0) { call.reject("This device does not support the recording format. Enter a transcript instead."); return; }
            try {
                microphone = new AudioRecord(MediaRecorder.AudioSource.VOICE_RECOGNITION, SAMPLE_RATE,
                    AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT, Math.max(size * 2, 8192));
                if (microphone.getState() != AudioRecord.STATE_INITIALIZED) throw new IllegalStateException();
                pcm = new ByteArrayOutputStream(); audioError = null;
                microphone.startRecording(); recording = true;
                final AudioRecord active = microphone;
                audioThread = new Thread(() -> capture(active), "NishaSpeakingPractice");
                audioThread.start(); call.resolve();
            } catch (Exception ignored) {
                recording = false;
                if (microphone != null) { microphone.release(); microphone = null; }
                pcm = null; call.reject("The microphone could not start. Close other recording apps or enter a transcript.");
            }
        }
    }

    private void capture(AudioRecord active) {
        short[] samples = new short[2048]; int total = 0;
        try {
            while (recording && total < MAX_SAMPLES) {
                int read = active.read(samples, 0, Math.min(samples.length, MAX_SAMPLES - total));
                if (read < 0) { if (recording) audioError = "The microphone was interrupted. Please record again."; break; }
                if (read == 0) continue;
                synchronized (audioLock) {
                    if (pcm == null) break;
                    for (int i = 0; i < read; i++) { pcm.write(samples[i] & 255); pcm.write((samples[i] >> 8) & 255); }
                }
                total += read;
            }
        } catch (Exception ignored) {
            if (recording) audioError = "The microphone was interrupted. Please record again.";
        } finally {
            recording = false;
            try { active.stop(); } catch (Exception ignored) {}
        }
    }

    private void finishCapture() throws InterruptedException {
        recording = false;
        AudioRecord active; Thread thread;
        synchronized (audioLock) { active = microphone; thread = audioThread; }
        if (active != null) try { active.stop(); } catch (Exception ignored) {}
        if (thread != null && thread != Thread.currentThread()) thread.join(2000);
        synchronized (audioLock) {
            if (thread != null && thread.isAlive()) throw new InterruptedException();
            if (microphone != null) { microphone.release(); microphone = null; }
            audioThread = null;
        }
    }

    @PluginMethod public void stopRecording(PluginCall call) {
        try {
            finishCapture(); byte[] data;
            synchronized (audioLock) {
                if (pcm == null || pcm.size() == 0) { pcm = null; call.reject("The recording is empty. Record a spoken response first."); return; }
                if (audioError != null) { pcm = null; call.reject(audioError); return; }
                data = pcm.toByteArray(); pcm = null;
            }
            ByteBuffer wav = ByteBuffer.allocate(44 + data.length).order(ByteOrder.LITTLE_ENDIAN);
            wav.put("RIFF".getBytes(StandardCharsets.US_ASCII)).putInt(36 + data.length).put("WAVEfmt ".getBytes(StandardCharsets.US_ASCII));
            wav.putInt(16).putShort((short)1).putShort((short)1).putInt(SAMPLE_RATE).putInt(SAMPLE_RATE * 2).putShort((short)2).putShort((short)16);
            wav.put("data".getBytes(StandardCharsets.US_ASCII)).putInt(data.length).put(data);
            JSObject result = new JSObject(); result.put("base64", Base64.encodeToString(wav.array(), Base64.NO_WRAP));
            result.put("mime", "audio/wav"); result.put("duration_seconds", data.length / (SAMPLE_RATE * 2.0)); call.resolve(result);
        } catch (Exception ignored) { cancelLocalRecording(); call.reject("The recording was interrupted. Please record again."); }
    }

    private void cancelLocalRecording() {
        synchronized (audioLock) { recordingGeneration++; permissionPending = false; }
        try { finishCapture(); } catch (Exception ignored) {}
        synchronized (audioLock) { pcm = null; audioError = null; }
    }

    @PluginMethod public void cancelRecording(PluginCall call) { cancelLocalRecording(); call.resolve(); }

    @PluginMethod public void speak(PluginCall call) {
        String text = call.getString("text", "");
        if (text.trim().isEmpty() || text.length() > 30000) { call.reject("This listening passage is unavailable."); return; }
        getActivity().runOnUiThread(() -> {
            if (!speechReady || speech == null) { call.reject("An English speech voice is not ready. Use the transcript, or enable an English voice in device settings."); return; }
            int availability = speech.setLanguage(Locale.forLanguageTag("en-AU"));
            if (availability < TextToSpeech.LANG_AVAILABLE) availability = speech.setLanguage(Locale.ENGLISH);
            if (availability < TextToSpeech.LANG_AVAILABLE) { call.reject("No English voice is installed. Use the transcript instead."); return; }
            speech.setSpeechRate(0.92f);
            // Android engines cap a single utterance at about 4,000 chars.
            int limit = Math.min(TextToSpeech.getMaxSpeechInputLength(), 3500);
            speech.stop();
            for (int offset = 0; offset < text.length(); offset += limit) {
                String chunk = text.substring(offset, Math.min(offset + limit, text.length()));
                if (speech.speak(chunk, TextToSpeech.QUEUE_ADD, null, UUID.randomUUID().toString()) == TextToSpeech.ERROR) { call.reject("Audio could not be played. Use the transcript instead."); return; }
            }
            call.resolve();
        });
    }

    private void stopLocalSpeech() { if (speech != null) speech.stop(); }
    @PluginMethod public void stopSpeech(PluginCall call) { stopLocalSpeech(); call.resolve(); }
    @Override protected void handleOnPause() {
        // A permission prompt can pause the activity before capture begins.
        // Stop and discard only actual audio, without cancelling that prompt.
        boolean hasCapture;
        synchronized (audioLock) { hasCapture = microphone != null || pcm != null; }
        if (hasCapture) cancelLocalRecording();
        stopLocalSpeech();
    }
    @Override protected void handleOnDestroy() { cancelLocalRecording(); if (speech != null) speech.shutdown(); }
    @Override public Boolean shouldOverrideLoad(Uri url) {
        String scheme = url.getScheme(); String host = url.getHost();
        if ("https".equals(scheme) && "localhost".equals(host) && url.getUserInfo() == null
            && (url.getPort() == -1 || url.getPort() == 443)) return null;
        // Only the bundled origin may host the privileged native bridge.
        // Public privacy/support pages open in the system browser instead.
        if ("https".equals(scheme) && ("nishaglobal.com".equals(host) || "www.nishaglobal.com".equals(host))
            && url.getUserInfo() == null && (url.getPort() == -1 || url.getPort() == 443)) {
            try { getActivity().startActivity(new Intent(Intent.ACTION_VIEW, url)); }
            catch (Exception ignored) { /* Leave the trusted app view intact. */ }
        }
        return true;
    }
}
