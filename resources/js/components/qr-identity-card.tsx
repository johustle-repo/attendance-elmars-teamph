import { useEffect, useState } from 'react';
import { Copy, Download, LoaderCircle } from 'lucide-react';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import {
    createQrDownloadName,
    createQrImageDataUrl,
    downloadQrCard,
} from '@/lib/qr';
import { Button } from '@/components/ui/button';

type Props = {
    name: string;
    value?: string | null;
    subtitle?: string | null;
    className?: string;
    compact?: boolean;
};

export function QrIdentityCard({
    name,
    value,
    subtitle,
    className,
    compact = false,
}: Props) {
    const [previewSrc, setPreviewSrc] = useState<string | null>(null);
    const [isDownloading, setIsDownloading] = useState(false);
    const [copiedText, copy] = useClipboard();

    useEffect(() => {
        let cancelled = false;

        async function renderQr() {
            if (!value) {
                setPreviewSrc(null);

                return;
            }

            const dataUrl = await createQrImageDataUrl(
                value,
                compact ? 420 : 520,
            );

            if (!cancelled) {
                setPreviewSrc(dataUrl);
            }
        }

        void renderQr();

        return () => {
            cancelled = true;
        };
    }, [compact, value]);

    async function handleDownload() {
        if (!value) {
            return;
        }

        setIsDownloading(true);

        try {
            await downloadQrCard({
                name,
                subtitle,
                value,
                filename: createQrDownloadName(name),
            });
        } finally {
            setIsDownloading(false);
        }
    }

    return (
        <div
            className={cn(
                'rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950/88 dark:shadow-black/20',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-lg font-semibold text-slate-950 dark:text-slate-50">
                        {name}
                    </p>
                    {subtitle && (
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {subtitle}
                        </p>
                    )}
                </div>

                <div className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-cyan-800 uppercase dark:bg-cyan-500/10 dark:text-cyan-200">
                    QR Card
                </div>
            </div>

            <div
                className={cn(
                    'mt-5 rounded-[1.5rem] bg-[radial-gradient(circle_at_top,_#cffafe,_#ffffff_58%)] p-4 dark:bg-[radial-gradient(circle_at_top,_rgba(34,211,238,0.18),_rgba(15,23,42,0.96)_62%)]',
                    compact ? 'p-3' : 'p-5',
                )}
            >
                {previewSrc ? (
                    <img
                        src={previewSrc}
                        alt={`${name} QR code`}
                        className="mx-auto aspect-square w-full max-w-[240px] rounded-2xl bg-white p-2"
                    />
                ) : (
                    <div className="mx-auto flex aspect-square w-full max-w-[240px] items-center justify-center rounded-2xl bg-slate-100 text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        No QR available
                    </div>
                )}
            </div>

            {value && (
                <p className="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-xs break-all text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    {value}
                </p>
            )}

            <div className="mt-4 flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => value && void copy(value)}
                    disabled={!value}
                    className="text-slate-500 hover:text-slate-700 dark:text-slate-300 dark:hover:text-slate-100"
                >
                    <Copy className="mr-2 h-4 w-4" />
                    {copiedText === value ? 'Copied' : 'Copy QR value'}
                </Button>
                <Button
                    type="button"
                    onClick={() => void handleDownload()}
                    disabled={!value || isDownloading}
                    className="bg-slate-950 text-white hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950 dark:hover:bg-cyan-400"
                >
                    {isDownloading ? (
                        <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                        <Download className="mr-2 h-4 w-4" />
                    )}
                    Download card
                </Button>
            </div>
        </div>
    );
}
