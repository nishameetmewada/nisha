import Foundation
import Capacitor
import AVFoundation
import Security
import UIKit
import WebKit

@objc(NishaDevicePlugin)
public class NishaDevicePlugin: CAPPlugin, CAPBridgedPlugin, AVAudioRecorderDelegate {
    public let identifier = "NishaDevicePlugin"
    public let jsName = "NishaDevice"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "getToken", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "setToken", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "clearToken", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "startRecording", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "stopRecording", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "cancelRecording", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "speak", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "stopSpeech", returnType: CAPPluginReturnPromise)
    ]
    private var service: String { (Bundle.main.bundleIdentifier ?? "com.nishaglobal.app") + ".session.v1" }
    private var recorder: AVAudioRecorder?
    private var recordingURL: URL?
    private var recordingTimer: Timer?
    private var recordingStarting = false
    private var recordingInterrupted = false
    private var recordingGeneration = 0
    private let speech = AVSpeechSynthesizer()
    private var observers: [NSObjectProtocol] = []

    public override func load() {
        observers.append(NotificationCenter.default.addObserver(forName: AVAudioSession.interruptionNotification, object: nil, queue: .main) { [weak self] notification in
            guard let type = notification.userInfo?[AVAudioSessionInterruptionTypeKey] as? UInt,
                  type == AVAudioSession.InterruptionType.began.rawValue else { return }
            self?.interruptCapture()
        })
        observers.append(NotificationCenter.default.addObserver(forName: UIApplication.didEnterBackgroundNotification, object: nil, queue: .main) { [weak self] _ in self?.interruptCapture() })
    }

    deinit {
        observers.forEach { NotificationCenter.default.removeObserver($0) }
        recordingTimer?.invalidate()
        recorder?.stop()
        if let url = recordingURL { try? FileManager.default.removeItem(at: url) }
    }

    private func keychainQuery() -> [String: Any] {
        [kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: service, kSecAttrAccount as String: "access_token"]
    }

    @objc func getToken(_ call: CAPPluginCall) {
        var query = keychainQuery(); query[kSecReturnData as String] = true; query[kSecMatchLimit as String] = kSecMatchLimitOne
        var result: CFTypeRef?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        if status == errSecItemNotFound { call.resolve(); return }
        guard status == errSecSuccess, let data = result as? Data, let token = String(data: data, encoding: .utf8) else {
            call.reject("Unlock this device and try signing in again."); return
        }
        call.resolve(["token": token])
    }

    @objc func setToken(_ call: CAPPluginCall) {
        guard let token = call.getString("token"), token.range(of: "^[A-Za-z0-9_-]{40,200}$", options: .regularExpression) != nil,
              let data = token.data(using: .utf8) else { call.reject("The account session is invalid."); return }
        let attributes: [String: Any] = [kSecValueData as String: data, kSecAttrAccessible as String: kSecAttrAccessibleWhenUnlockedThisDeviceOnly]
        let updated = SecItemUpdate(keychainQuery() as CFDictionary, attributes as CFDictionary)
        if updated == errSecSuccess { call.resolve(); return }
        guard updated == errSecItemNotFound else { call.reject("Secure account storage is unavailable."); return }
        var item = keychainQuery(); attributes.forEach { item[$0.key] = $0.value }
        if SecItemAdd(item as CFDictionary, nil) == errSecSuccess { call.resolve() }
        else { call.reject("Secure account storage is unavailable.") }
    }

    @objc func clearToken(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            self.discardCapture(); self.speech.stopSpeaking(at: .immediate)
            let status = SecItemDelete(self.keychainQuery() as CFDictionary)
            guard status == errSecSuccess || status == errSecItemNotFound else { call.reject("The saved account session could not be cleared."); return }
            call.resolve()
        }
    }

    @objc func startRecording(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            guard self.recorder == nil && !self.recordingStarting else { call.reject("Finish or discard the current recording first."); return }
            self.recordingStarting = true; self.recordingGeneration += 1; let generation = self.recordingGeneration
            AVAudioSession.sharedInstance().requestRecordPermission { granted in
                DispatchQueue.main.async {
                    guard generation == self.recordingGeneration else { call.reject("Recording was cancelled."); return }
                    self.recordingStarting = false
                    guard granted else { call.reject("Microphone permission was declined. You can enter a transcript instead."); return }
                    self.beginCapture(call)
                }
            }
        }
    }

    private func beginCapture(_ call: CAPPluginCall) {
        let url = FileManager.default.temporaryDirectory.appendingPathComponent("nisha-practice-" + UUID().uuidString + ".wav")
        do {
            speech.stopSpeaking(at: .immediate)
            let session = AVAudioSession.sharedInstance()
            try session.setCategory(.playAndRecord, mode: .measurement, options: [.defaultToSpeaker, .allowBluetooth])
            try session.setActive(true)
            let settings: [String: Any] = [AVFormatIDKey: Int(kAudioFormatLinearPCM), AVSampleRateKey: 16000.0, AVNumberOfChannelsKey: 1,
                AVLinearPCMBitDepthKey: 16, AVLinearPCMIsBigEndianKey: false, AVLinearPCMIsFloatKey: false]
            let active = try AVAudioRecorder(url: url, settings: settings)
            active.delegate = self
            guard active.prepareToRecord() && active.record(forDuration: 300) else { throw NSError(domain: "NISHA", code: 1) }
            recordingURL = url; recorder = active; recordingInterrupted = false
            try? FileManager.default.setAttributes([.protectionKey: FileProtectionType.complete], ofItemAtPath: url.path)
            var excluded = url; var values = URLResourceValues(); values.isExcludedFromBackup = true; try? excluded.setResourceValues(values)
            recordingTimer = Timer.scheduledTimer(withTimeInterval: 300, repeats: false) { [weak self] _ in self?.recorder?.stop() }
            call.resolve()
        } catch {
            try? FileManager.default.removeItem(at: url); discardCapture()
            call.reject("The microphone could not start. Close other recording apps or enter a transcript.")
        }
    }

    private func interruptCapture() {
        let interrupted = recordingStarting || recorder != nil
        discardCapture()
        recordingInterrupted = interrupted
        speech.stopSpeaking(at: .immediate)
    }

    private func discardCapture() {
        recordingGeneration += 1; recordingStarting = false; recordingTimer?.invalidate(); recordingTimer = nil
        recorder?.stop(); recorder = nil
        if let url = recordingURL { try? FileManager.default.removeItem(at: url) }
        recordingURL = nil; recordingInterrupted = false
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
    }

    @objc func stopRecording(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            if self.recordingInterrupted {
                self.recordingInterrupted = false
                call.reject("The recording was interrupted. Please record again."); return
            }
            guard let active = self.recorder, let url = self.recordingURL else { call.reject("There is no recording to finish."); return }
            active.stop(); self.recordingTimer?.invalidate(); self.recordingTimer = nil
            defer { self.discardCapture() }
            guard !self.recordingInterrupted else { call.reject("The recording was interrupted. Please record again."); return }
            do {
                let data = try Data(contentsOf: url)
                guard data.count >= 100 && data.count <= 10 * 1024 * 1024,
                      String(data: data.prefix(4), encoding: .ascii) == "RIFF",
                      String(data: data.subdata(in: 8..<12), encoding: .ascii) == "WAVE" else {
                    call.reject("The recording is empty or unsupported. Please record again."); return
                }
                let file = try AVAudioFile(forReading: url)
                let duration = Double(file.length) / file.fileFormat.sampleRate
                guard duration > 0 && duration <= 301 else { call.reject("Keep the recording under five minutes."); return }
                call.resolve(["base64": data.base64EncodedString(), "mime": "audio/wav", "duration_seconds": duration])
            } catch { call.reject("The recording could not be prepared. Please record again.") }
        }
    }

    @objc func cancelRecording(_ call: CAPPluginCall) { DispatchQueue.main.async { self.discardCapture(); call.resolve() } }

    @objc func speak(_ call: CAPPluginCall) {
        guard let text = call.getString("text"), !text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty, text.count <= 30000 else { call.reject("This listening passage is unavailable."); return }
        DispatchQueue.main.async {
            guard self.recorder == nil && !self.recordingStarting else { call.reject("Finish your recording before playing listening audio."); return }
            guard let voice = AVSpeechSynthesisVoice(language: "en-AU") ?? AVSpeechSynthesisVoice(language: "en-US") else { call.reject("No English voice is available. Use the transcript instead."); return }
            do {
                try AVAudioSession.sharedInstance().setCategory(.playback, mode: .spokenAudio, options: [.mixWithOthers])
                try AVAudioSession.sharedInstance().setActive(true)
                self.speech.stopSpeaking(at: .immediate)
                let utterance = AVSpeechUtterance(string: text); utterance.voice = voice; utterance.rate = AVSpeechUtteranceDefaultSpeechRate * 0.92
                self.speech.speak(utterance); call.resolve()
            } catch { call.reject("Audio could not be played. Use the transcript instead.") }
        }
    }

    @objc func stopSpeech(_ call: CAPPluginCall) { DispatchQueue.main.async { self.speech.stopSpeaking(at: .immediate); call.resolve() } }
    public override func shouldOverrideLoad(_ navigationAction: WKNavigationAction) -> NSNumber? {
        guard let url = navigationAction.request.url else { return NSNumber(value: true) }
        if url.scheme == "capacitor" && url.host == "localhost" && url.user == nil && url.port == nil { return nil }
        // Keep public website HTML outside the view that can read the keychain.
        if url.scheme == "https" && ["nishaglobal.com", "www.nishaglobal.com"].contains(url.host ?? ""),
           url.user == nil, url.port == nil || url.port == 443,
           navigationAction.navigationType == .linkActivated {
            DispatchQueue.main.async { UIApplication.shared.open(url) }
        }
        return NSNumber(value: true)
    }
}
