import { createInertiaApp } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BridgeLayout from '@/layouts/BridgeLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Mail Bridge';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('installer/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [BridgeLayout, SettingsLayout];
            default:
                return BridgeLayout;
        }
    },
    progress: {
        color: '#3b82f6',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
