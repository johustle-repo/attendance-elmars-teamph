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

            const dataUrl = await createQrImageDataUrl(value, compact ? 420 : 520);

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
                'rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-lg font-semibold text-slate-950">
                        {name}
                    </p>
                    {subtitle && (
                        <p className="text-sm text-slate-500">{subtitle}</p>
                    )}
                </div>

                <div className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-800">
                    QR Card
                </div>
            </div>

            <div
                className={cn(
                    'mt-5 rounded-[1.5rem] bg-[radial-gradient(circle_at_top,_#cffafe,_#ffffff_58%)] p-4',
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
                    <div className="mx-auto flex aspect-square w-full max-w-[240px] items-center justify-center rounded-2xl bg-slate-100 text-sm text-slate-500">
                        No QR available
                    </div>
                )}
            </div>

            {value && (
                <p className="mt-4 break-all rounded-2xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    {value}
                </p>
            )}

            <div className="mt-4 flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => value && void copy(value)}
                    disabled={!value}
                    className="text-slate-400 hover:text-slate-500"
                >
                    <Copy className="mr-2 h-4 w-4" />
                    {copiedText === value ? 'Copied' : 'Copy QR value'}
                </Button>
                <Button
                    type="button"
                    onClick={() => void handleDownload()}
                    disabled={!value || isDownloading}
                    className="bg-slate-950 text-white hover:bg-slate-800"
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
