let lastPagePath = null;

/**
 * Animasi masuk hanya berjalan saat alamat halaman berubah. Halaman bisa dipasang
 * ulang di alamat yang sama (mis. setelah mengirim form), dan itu tidak perlu dianimasikan.
 */
export function shouldAnimatePageEntry(url) {
    const path = url.split('?')[0];
    const hasPathChanged = path !== lastPagePath;
    lastPagePath = path;
    return hasPathChanged;
}
