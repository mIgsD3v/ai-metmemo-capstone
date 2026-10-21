/**
 * Phone photos are several MB: scale down to maxSize px on the longest side and re-encode as JPEG,
 * so uploads stay fast and under the server limit. Falls back to the original if it can't be read.
 */
export const shrink = (file, maxSize = 1600, name = 'photo.jpg') => new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
        const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.width * scale);
        canvas.height = Math.round(img.height * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        canvas.toBlob((blob) => resolve(blob ? new File([blob], name, { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
    };

    img.onerror = () => {
        URL.revokeObjectURL(url);
        resolve(file);
    };

    img.src = url;
});
