import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatNumber(value: number): string {
    const absoluteValue = Math.abs(value);

    if (absoluteValue < 1_000) {
        return `${value}`;
    }

    const thresholds: Array<{ min: number; suffix: string }> = [
        { min: 1_000_000_000, suffix: 'B' },
        { min: 1_000_000, suffix: 'M' },
        { min: 1_000, suffix: 'k' },
    ];

    for (const threshold of thresholds) {
        if (absoluteValue >= threshold.min) {
            const scaled = (value / threshold.min).toFixed(1);
            const compact = scaled.endsWith('.0') ? scaled.slice(0, -2) : scaled;

            return `${compact}${threshold.suffix}`;
        }
    }

    return `${value}`;
}

export function formatExactNumber(value: number): string {
    return new Intl.NumberFormat('en-US').format(value);
}
