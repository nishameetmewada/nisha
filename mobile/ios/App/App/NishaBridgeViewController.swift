import Capacitor

final class NishaBridgeViewController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        bridge?.registerPluginInstance(NishaDevicePlugin())
    }
}
