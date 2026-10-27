/**
 * Derive the avatar initials for a person.
 *
 * A name gives the first letters of its first two words; an email-only person
 * gets the first two characters of the address, so the same shadow account
 * shows the same initials in every avatar.
 */
export function initials(
    name: string | null | undefined,
    email?: string | null,
): string {
    const trimmedName = name?.trim();

    if (trimmedName) {
        return trimmedName
            .split(/\s+/)
            .map((part) => Array.from(part)[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    }

    const trimmedEmail = email?.trim();

    if (!trimmedEmail) {
        return '?';
    }

    return Array.from(trimmedEmail).slice(0, 2).join('').toUpperCase();
}
