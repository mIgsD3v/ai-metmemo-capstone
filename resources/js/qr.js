import QRCode from 'qrcode';
import { toast } from '@/toast';

/**
 * Save a card's QR code as a 1024px PNG (white background and margin, so it scans from a screen and prints
 * sharply), named after the card. Phones get the share sheet, where "Save Image" puts it in Photos: a plain
 * download on iPhone only opens the image in a new tab. Everything else downloads the file.
 */
export async function downloadQr(url, name) {
    const slug = name.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-') || 'card';
    const filename = `metmemo-qr-${slug}.png`;

    let blob;
    try {
        const dataUrl = await QRCode.toDataURL(url, { width: 1024, margin: 2, errorCorrectionLevel: 'M' });
        blob = await (await fetch(dataUrl)).blob();
    } catch {
        toast('Could not make the QR code. Please try again.', 'danger');
        return;
    }

    const file = new File([blob], filename, { type: 'image/png' });
    const touchDevice = window.matchMedia('(pointer: coarse)').matches;

    if (touchDevice && navigator.canShare?.({ files: [file] })) {
        try {
            await navigator.share({ files: [file], title: `${name}: QR code` });
        } catch {
            // Closed the share sheet: nothing to do.
        }
        return;
    }

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);

    toast('QR code downloaded', 'success');
}
