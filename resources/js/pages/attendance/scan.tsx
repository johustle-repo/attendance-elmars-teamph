import { ChangeEvent, FormEvent, useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Camera,
    CameraOff,
    LogIn,
    LogOut,
    ImageUp,
    Keyboard,
    QrCode,
    RefreshCcw,
    ShieldCheck,
} from 'lucide-react';
import QrScanner from 'qr-scanner';
import { FlashMessage } from '@/components/flash-message';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { AttendanceItem, Flash, User } from '@/types';

type Props = {
    latestAttendances: AttendanceItem[];
    teamCount: number;
};

export default function AttendanceScan({ latestAttendances, teamCount }: Props) {
    const { flash, auth } = usePage().props as {
        flash: Flash;
        auth: { user: User | null };
    };
    const form = useForm({
        qr_code: '',
        entry_type: 'time_in',
    });

    const [cameraError, setCameraError] = useState<string | null>(null);
    const [cameraState, setCameraState] = useState<
        'idle' | 'starting' | 'scanning'
    >('idle');
    const [availableCameras, setAvailableCameras] = useState<
        Array<{ id: string; label: string }>
    >([]);
    const [selectedCamera, setSelectedCamera] = useState<string>('environment');
    const [detectedValue, setDetectedValue] = useState<string | null>(null);
    const [isReadingImage, setIsReadingImage] = useState(false);

    const videoRef = useRef<HTMLVideoElement | null>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const isSubmittingRef = useRef(false);
    const isLocalHost =
        typeof window !== 'undefined' &&
        ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const canUseLiveCamera =
        typeof window !== 'undefined' &&
        (window.isSecureContext || isLocalHost);
    const shouldHidePublicActions =
        auth.user?.role === 'admin' || auth.user?.role === 'super_admin';

    function disposeScanner() {
        scannerRef.current?.stop();
        scannerRef.current?.destroy();
        scannerRef.current = null;
    }

    function stopScanner() {
        disposeScanner();
        setCameraState('idle');
    }

    function submitCode(value: string) {
        if (isSubmittingRef.current) {
            return;
        }

        isSubmittingRef.current = true;
        setDetectedValue(value);

        router.post(
            '/scan',
            {
                qr_code: value,
                entry_type: form.data.entry_type,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    isSubmittingRef.current = false;
                    stopScanner();
                },
            },
        );
    }

    async function loadCameras(requestLabels = false) {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices) {
            return;
        }

        try {
            const cameras = await QrScanner.listCameras(requestLabels);
            setAvailableCameras(cameras);

            if (!cameras.length) {
                return;
            }

            if (
                selectedCamera === 'environment' ||
                !cameras.some((camera) => camera.id === selectedCamera)
            ) {
                setSelectedCamera(cameras[0].id);
            }
        } catch {
            setAvailableCameras([]);
        }
    }

    function mapCameraError(error: unknown) {
        if (!(error instanceof Error)) {
            return 'Camera access was blocked or unavailable. Check browser permissions and try again.';
        }

        switch (error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                return 'Camera permission was denied. Allow webcam access in your browser, then try again.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'No webcam was detected on this device.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'The webcam is already being used by another app or browser tab. Close the other app and try again.';
            case 'OverconstrainedError':
            case 'ConstraintNotSatisfiedError':
                return 'The selected webcam could not be opened. Try another listed camera.';
            case 'SecurityError':
                return 'The browser blocked webcam access for security reasons. Open this app on HTTPS or localhost.';
            default:
                return error.message ||
                    'Camera access was blocked or unavailable. Check browser permissions and try again.';
        }
    }

    async function startScanner(preferredCamera?: string) {
        setCameraError(null);

        if (
            typeof window === 'undefined' ||
            typeof navigator === 'undefined' ||
            !navigator.mediaDevices?.getUserMedia
        ) {
            setCameraError(
                'This browser does not support camera access. You can still scan from an uploaded QR image or enter the QR value manually.',
            );

            return;
        }

        if (!canUseLiveCamera) {
            setCameraError(
                'Camera scanning requires HTTPS or localhost. Open this project from a secure local address to use the live scanner.',
            );

            return;
        }

        if (!videoRef.current) {
            return;
        }

        setCameraState('starting');

        try {
            disposeScanner();

            const nextCamera =
                preferredCamera ||
                selectedCamera ||
                availableCameras[0]?.id ||
                'environment';

            const scanner = new QrScanner(
                videoRef.current,
                (result) => {
                    submitCode(result.data);
                },
                {
                    preferredCamera: nextCamera,
                    highlightScanRegion: true,
                    highlightCodeOutline: true,
                    maxScansPerSecond: 12,
                    returnDetailedScanResult: true,
                    onDecodeError: (error) => {
                        if (String(error) !== QrScanner.NO_QR_CODE_FOUND) {
                            setCameraError(
                                'The camera opened, but QR detection hit an error. Try better lighting, upload a QR image, or enter the code manually.',
                            );
                        }
                    },
                },
            );

            scannerRef.current = scanner;
            await scanner.start();
            scanner.setInversionMode('both');
            setCameraState('scanning');
            await loadCameras(true);
        } catch (error) {
            setCameraState('idle');
            setCameraError(mapCameraError(error));
        }
    }

    async function handleCameraChange(cameraId: string) {
        setSelectedCamera(cameraId);
        setCameraError(null);

        if (!scannerRef.current || cameraState !== 'scanning') {
            return;
        }

        try {
            await scannerRef.current.setCamera(cameraId);
        } catch (error) {
            setCameraError(mapCameraError(error));
        }
    }

    async function handleImageUpload(event: ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setCameraError(null);
        setIsReadingImage(true);

        try {
            const result = await QrScanner.scanImage(file, {
                alsoTryWithoutScanRegion: true,
                returnDetailedScanResult: true,
            });

            submitCode(result.data);
        } catch {
            setCameraError(
                'No QR code was detected in that image. Try a clearer screenshot or use manual entry.',
            );
        } finally {
            setIsReadingImage(false);
            event.target.value = '';
        }
    }

    function handleManualSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/scan', {
            preserveScroll: true,
            onFinish: () => {
                stopScanner();
            },
        });
    }

    useEffect(() => {
        void loadCameras();

        const handleDeviceChange = () => {
            void loadCameras(true);
        };

        navigator.mediaDevices?.addEventListener?.(
            'devicechange',
            handleDeviceChange,
        );

        return () => {
            navigator.mediaDevices?.removeEventListener?.(
                'devicechange',
                handleDeviceChange,
            );
            disposeScanner();
        };
    }, []);

    return (
        <>
            <Head title="QR Scanner" />

            <div className="min-h-screen bg-[linear-gradient(180deg,_#f8fafc_0%,_#f0fdfa_48%,_#eff6ff_100%)]">
                <div className="mx-auto max-w-7xl px-6 py-8 lg:px-10">
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-700">
                                Attendance Scanner
                            </p>
                            <h1 className="text-3xl font-semibold text-slate-950">
                                Reliable QR check-in station
                            </h1>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Button
                                asChild
                                variant="outline"
                                className="border-slate-300 bg-white/80 backdrop-blur hover:bg-white"
                            >
                                <Link href="/">Return to home</Link>
                            </Button>
                            {!shouldHidePublicActions && (
                                <Button
                                    asChild
                                    className="bg-slate-950 text-white hover:bg-slate-800"
                                >
                                    <Link href="/login">Admin login</Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1.12fr_0.88fr]">
                        <div className="space-y-6">
                            <FlashMessage flash={flash} />

                            <Card className="gap-0 overflow-hidden border-cyan-100 bg-white/95 py-0 shadow-lg shadow-cyan-950/5">
                                <div className="border-b border-cyan-900/10 bg-[linear-gradient(135deg,_#082f49_0%,_#155e75_52%,_#0f766e_100%)] p-6 text-white sm:p-8">
                                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_240px] lg:items-center">
                                        <div className="space-y-4">
                                            <div className="inline-flex w-fit rounded-full border border-white/15 bg-white/8 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-100/90">
                                                Live camera / image upload / manual entry
                                            </div>
                                            <CardTitle className="max-w-xl text-3xl leading-tight sm:text-[2.1rem]">
                                                Open the camera and scan instantly
                                            </CardTitle>
                                            <CardDescription className="max-w-2xl text-base leading-7 text-cyan-50/85">
                                                Use the live webcam, a QR image,
                                                or manual code entry to record
                                                attendance quickly and reliably.
                                            </CardDescription>
                                        </div>

                                        <div className="rounded-[1.75rem] border border-white/12 bg-white/10 px-5 py-5 text-left backdrop-blur">
                                            <p className="text-xs uppercase tracking-[0.2em] text-cyan-100/80">
                                                Registered users
                                            </p>
                                            <p className="mt-3 text-4xl font-semibold leading-none">
                                                {teamCount}
                                            </p>
                                            <p className="mt-3 text-sm leading-6 text-cyan-50/75">
                                                Ready for QR attendance scanning
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <CardContent className="space-y-5 p-6 pt-6">
                                    <div className="relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-950">
                                        <video
                                            ref={videoRef}
                                            autoPlay
                                            playsInline
                                            muted
                                            className="aspect-video w-full object-cover"
                                        />
                                        {cameraState === 'idle' && (
                                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950/65 text-center text-white">
                                                <Camera className="h-10 w-10 text-cyan-300" />
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        Camera ready
                                                    </p>
                                                    <p className="text-sm text-slate-200">
                                                        Press start to open the
                                                        scanner and point it at a
                                                        QR code.
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex flex-wrap gap-3">
                                        {availableCameras.length > 0 && (
                                            <select
                                                value={selectedCamera}
                                                onChange={(event) =>
                                                    void handleCameraChange(
                                                        event.target.value,
                                                    )
                                                }
                                                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border bg-white px-3 text-sm text-slate-900 outline-none focus-visible:ring-[3px]"
                                            >
                                                {availableCameras.map(
                                                    (camera) => (
                                                        <option
                                                            key={camera.id}
                                                            value={camera.id}
                                                        >
                                                            {camera.label ||
                                                                'Web camera'}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        )}

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => void loadCameras(true)}
                                            disabled={cameraState === 'starting'}
                                        >
                                            <RefreshCcw className="mr-2 h-4 w-4" />
                                            Refresh webcams
                                        </Button>

                                        <Button
                                            type="button"
                                            onClick={() =>
                                                void startScanner(
                                                    selectedCamera,
                                                )
                                            }
                                            disabled={
                                                form.processing ||
                                                cameraState === 'starting'
                                            }
                                            className="bg-cyan-600 text-white hover:bg-cyan-700"
                                        >
                                            {cameraState === 'scanning' ? (
                                                <RefreshCcw className="mr-2 h-4 w-4" />
                                            ) : (
                                                <Camera className="mr-2 h-4 w-4" />
                                            )}
                                            {cameraState === 'starting'
                                                ? 'Opening camera...'
                                                : cameraState === 'scanning'
                                                  ? 'Restart scanner'
                                                  : 'Start camera scanner'}
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={stopScanner}
                                            disabled={cameraState === 'idle'}
                                        >
                                            <CameraOff className="mr-2 h-4 w-4" />
                                            Stop scanner
                                        </Button>
                                    </div>

                                    <div className="flex flex-wrap gap-3">
                                        <Button
                                            type="button"
                                            variant={
                                                form.data.entry_type ===
                                                'time_in'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'entry_type',
                                                    'time_in',
                                                )
                                            }
                                            className={
                                                form.data.entry_type ===
                                                'time_in'
                                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                                    : ''
                                            }
                                        >
                                            <LogIn className="mr-2 h-4 w-4" />
                                            Time In
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={
                                                form.data.entry_type ===
                                                'time_out'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'entry_type',
                                                    'time_out',
                                                )
                                            }
                                            className={
                                                form.data.entry_type ===
                                                'time_out'
                                                    ? 'bg-amber-600 text-white hover:bg-amber-700'
                                                    : ''
                                            }
                                        >
                                            <LogOut className="mr-2 h-4 w-4" />
                                            Time Out
                                        </Button>
                                    </div>

                                    {!canUseLiveCamera && (
                                        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                            Live webcam scanning only works on
                                            `https://` or `http://localhost`.
                                            If you opened this project from a
                                            LAN IP address, the browser will
                                            block the camera.
                                        </div>
                                    )}

                                    {detectedValue && (
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                            Last detected QR: {detectedValue}
                                            <span className="ml-2 font-semibold">
                                                ({form.data.entry_type ===
                                                'time_in'
                                                    ? 'Time In'
                                                    : 'Time Out'})
                                            </span>
                                        </div>
                                    )}

                                    {cameraError && (
                                        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                            {cameraError}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="grid gap-6 lg:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <ImageUp className="h-5 w-5 text-cyan-700" />
                                            Scan from image
                                        </CardTitle>
                                        <CardDescription>
                                            Upload a screenshot or photo of a QR
                                            code if the live camera is blocked.
                                            The selected action will still be
                                            recorded as {form.data.entry_type ===
                                            'time_in'
                                                ? 'Time In'
                                                : 'Time Out'}.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Input
                                            type="file"
                                            accept="image/*"
                                            onChange={(event) =>
                                                void handleImageUpload(event)
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={isReadingImage}
                                        >
                                            <ImageUp className="mr-2 h-4 w-4" />
                                            {isReadingImage
                                                ? 'Reading image...'
                                                : 'Choose QR image above'}
                                        </Button>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Keyboard className="h-5 w-5 text-cyan-700" />
                                            Manual QR entry
                                        </CardTitle>
                                        <CardDescription>
                                            Paste the QR value directly for quick
                                            testing or fallback use. The selected
                                            action below will be saved with the
                                            scan.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <form
                                            onSubmit={handleManualSubmit}
                                            className="space-y-4"
                                        >
                                            <div className="grid gap-2">
                                                <Input
                                                    value={form.data.qr_code}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'qr_code',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="attendance:your-qr-token"
                                                />
                                                <InputError
                                                    message={form.errors.qr_code}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={form.processing}
                                                className="bg-slate-950 text-white hover:bg-slate-800"
                                            >
                                                <QrCode className="mr-2 h-4 w-4" />
                                                Record{' '}
                                                {form.data.entry_type ===
                                                'time_in'
                                                    ? 'Time In'
                                                    : 'Time Out'}
                                            </Button>
                                        </form>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <div className="space-y-6">
                            <Card className="border-slate-200 bg-white/85 backdrop-blur">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ShieldCheck className="h-5 w-5 text-emerald-600" />
                                        Better scanning flow
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm text-slate-600">
                                    <p>
                                        1. Choose Time In or Time Out, then use
                                        the live camera scanner.
                                    </p>
                                    <p>
                                        2. If camera access fails, upload a QR
                                        image or paste the raw QR value.
                                    </p>
                                    <p>
                                        3. Every successful scan records the
                                        timestamp and syncs online when Firebase
                                        is configured.
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent check-ins</CardTitle>
                                    <CardDescription>
                                        Latest successful attendance records.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {latestAttendances.length === 0 ? (
                                        <p className="rounded-2xl border border-dashed border-slate-200 p-6 text-sm text-slate-500">
                                            No attendance logs yet.
                                        </p>
                                    ) : (
                                        latestAttendances.map((attendance) => (
                                            <div
                                                key={attendance.id}
                                                className="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <div>
                                                        <p className="font-medium text-slate-900">
                                                            {attendance.user_name}
                                                        </p>
                                                        <p className="text-sm text-slate-500">
                                                            {attendance.employee_code ??
                                                                'No employee code'}
                                                        </p>
                                                        <p className="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                                            {attendance.entry_type_label ??
                                                                'Attendance'}
                                                        </p>
                                                    </div>
                                                    <div className="text-right text-sm text-slate-500">
                                                        <p>
                                                            {attendance.recorded_date}
                                                        </p>
                                                        <p>
                                                            {attendance.recorded_time}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

