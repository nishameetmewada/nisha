import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.nishaglobal.app',
  appName: 'NISHA',
  webDir: 'dist',
  backgroundColor: '#f8f7f2',
  server: { androidScheme: 'https', cleartext: false },
  android: { allowMixedContent: false, captureInput: true, webContentsDebuggingEnabled: false },
  ios: { contentInset: 'never', preferredContentMode: 'mobile' },
};

export default config;
