import manifest from '../../icons/products/manifest.json';

/**
 * A line icon of the product set, as the manifest lists it.
 */
export interface ProductIconEntry {
    key: string;
    de: string;
    en: string;
    section: string;
    source: string;
}

const SECTION_PREFIX = 'section-';
const FALLBACK = 'section-other';

const drawings = import.meta.glob<string>('../../icons/products/*.svg', {
    eager: true,
    query: '?raw',
    import: 'default',
});

const shapes = new Map(
    Object.entries(drawings).map(([path, drawing]) => [
        path.slice(path.lastIndexOf('/') + 1, -'.svg'.length),
        drawing.replace(/^<svg[^>]*>/, '').replace(/<\/svg>\s*$/, ''),
    ]),
);

/**
 * The icons a product can be pictured with, without the generic icons of
 * the store sections.
 */
export const PRODUCT_ICONS: ProductIconEntry[] = (manifest as ProductIconEntry[]).filter(
    (entry) => !entry.key.startsWith(SECTION_PREFIX),
);

/**
 * The shapes of the icon of a product: its own, otherwise the generic
 * one of its store section, otherwise a basket.
 */
export function productIconShapes(
    key: string | null | undefined,
    sectionKey: string | null | undefined,
): string {
    const section = sectionKey ? SECTION_PREFIX + sectionKey.replaceAll('_', '-') : FALLBACK;

    return shapes.get(key ?? '') ?? shapes.get(section) ?? shapes.get(FALLBACK) ?? '';
}
