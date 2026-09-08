const MIN_COMPRESS_BYTES = 1.5 * 1024 * 1024;
const MAX_EDGE = 2560;
const QUALITY = 0.82;
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif'];

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Image illisible'));
        };
        img.src = url;
    });
}

function canvasToBlob(canvas, type, quality) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), type, quality);
    });
}

export async function compressFile(file) {
    if (!(file instanceof Blob) || !IMAGE_TYPES.includes(file.type) || file.size < MIN_COMPRESS_BYTES) {
        return { file, originalSize: file?.size ?? 0, compressedSize: file?.size ?? 0, compressed: false };
    }

    try {
        const img = await loadImage(file);
        const scale = Math.min(1, MAX_EDGE / Math.max(img.width, img.height));
        if (scale === 1 && file.size < 3 * 1024 * 1024 && file.type === 'image/jpeg') {
            return { file, originalSize: file.size, compressedSize: file.size, compressed: false };
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(img.width * scale));
        canvas.height = Math.max(1, Math.round(img.height * scale));
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        const blob = await canvasToBlob(canvas, 'image/jpeg', QUALITY);
        if (!blob || blob.size >= file.size) {
            return { file, originalSize: file.size, compressedSize: file.size, compressed: false };
        }

        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        const compressedFile = new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
        return {
            file: compressedFile,
            originalSize: file.size,
            compressedSize: compressedFile.size,
            compressed: true
        };
    } catch {
        return { file, originalSize: file.size, compressedSize: file.size, compressed: false };
    }
}
