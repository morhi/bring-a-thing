import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// The heading scale utilities from app.css are font sizes; unknown, tailwind-merge reads them as text colours and drops them next to text-fg
const twMerge = extendTailwindMerge({
    extend: {
        classGroups: {
            'font-size': [{ text: ['h1', 'h2'] }],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
