import type { Auth, Flash } from '@/types/auth';

declare global {
    interface DetectedBarcode {
        rawValue?: string;
    }

    interface BarcodeDetector {
        detect(source: ImageBitmapSource): Promise<DetectedBarcode[]>;
    }

    interface BarcodeDetectorConstructor {
        new (options?: { formats?: string[] }): BarcodeDetector;
        getSupportedFormats?: () => Promise<string[]>;
    }

    var BarcodeDetector: BarcodeDetectorConstructor | undefined;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: Flash;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
