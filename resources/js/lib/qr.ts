import QRCode from 'qrcode';

type DownloadQrCardOptions = {
    name: string;
    value: string;
    subtitle?: string | null;
    filename?: string;
};

const qrColors = {
    dark: '#0f172a',
    light: '#ffffff',
};

export async function createQrImageDataUrl(
    value?: string | null,
    width = 520,
) {
    if (!value) {
        return null;
    }

    return QRCode.toDataURL(value, {
        errorCorrectionLevel: 'M',
        margin: 2,
        width,
        color: qrColors,
    });
}

export function createQrDownloadName(name: string) {
    return `${name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'attendance-user'}-qr-card.png`;
}

export async function downloadQrCard({
    name,
    value,
    subtitle,
    filename,
}: DownloadQrCardOptions) {
    const qrDataUrl = await createQrImageDataUrl(value, 900);

    if (!qrDataUrl) {
        return;
    }

    const qrImage = await loadImage(qrDataUrl);
    const canvas = document.createElement('canvas');
    canvas.width = 1400;
    canvas.height = 1800;

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    const gradient = context.createLinearGradient(0, 0, canvas.width, canvas.height);
    gradient.addColorStop(0, '#0f172a');
    gradient.addColorStop(0.45, '#155e75');
    gradient.addColorStop(1, '#10b981');
    context.fillStyle = gradient;
    context.fillRect(0, 0, canvas.width, canvas.height);

    context.fillStyle = 'rgba(255, 255, 255, 0.16)';
    context.beginPath();
    context.arc(1180, 190, 180, 0, Math.PI * 2);
    context.fill();

    roundRect(context, 110, 120, 1180, 1560, 54);
    context.fillStyle = '#f8fafc';
    context.fill();

    context.fillStyle = '#0f172a';
    context.font = '700 52px "Segoe UI"';
    context.fillText("Elmar's Team PH", 190, 250);

    context.fillStyle = '#475569';
    context.font = '500 28px "Segoe UI"';
    context.fillText('Official team QR identity card', 190, 305);

    roundRect(context, 200, 370, 1000, 950, 38);
    context.fillStyle = '#ffffff';
    context.shadowColor = 'rgba(15, 23, 42, 0.12)';
    context.shadowBlur = 32;
    context.shadowOffsetY = 16;
    context.fill();
    context.shadowColor = 'transparent';
    context.drawImage(qrImage, 260, 430, 880, 830);

    context.fillStyle = '#0f172a';
    context.font = '700 62px "Segoe UI"';
    context.fillText(name, 190, 1450);

    if (subtitle) {
        context.fillStyle = '#334155';
        context.font = '500 34px "Segoe UI"';
        context.fillText(subtitle, 190, 1510);
    }

    context.fillStyle = '#64748b';
    context.font = '500 24px "Segoe UI"';
    context.fillText(value, 190, 1588, 1015);

    context.fillStyle = '#0f172a';
    context.font = '600 28px "Segoe UI"';
    context.fillText('Scan to record attendance', 190, 1650);

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = filename ?? createQrDownloadName(name);
    link.click();
}

function roundRect(
    context: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
) {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.lineTo(x + width - radius, y);
    context.quadraticCurveTo(x + width, y, x + width, y + radius);
    context.lineTo(x + width, y + height - radius);
    context.quadraticCurveTo(
        x + width,
        y + height,
        x + width - radius,
        y + height,
    );
    context.lineTo(x + radius, y + height);
    context.quadraticCurveTo(x, y + height, x, y + height - radius);
    context.lineTo(x, y + radius);
    context.quadraticCurveTo(x, y, x + radius, y);
    context.closePath();
}

function loadImage(src: string) {
    return new Promise<HTMLImageElement>((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = src;
    });
}
