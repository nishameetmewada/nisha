package com.nishaglobal.app;

import com.getcapacitor.BridgeActivity;
import android.os.Bundle;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(NishaDevicePlugin.class);
        super.onCreate(savedInstanceState);
    }
}
