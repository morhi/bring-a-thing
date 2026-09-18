import type { PollOption, PollResponseStatus } from '@/types';

/** The given member's yes/no/maybe response on a poll option, or null if they haven't responded. */
export function responseFor(
    option: PollOption,
    userId: number,
): PollResponseStatus | null {
    return (
        option.responses?.find((response) => response.user_id === userId)
            ?.status ?? null
    );
}

export type AvailabilityWarning = {
    label: string;
};

/**
 * Whether claiming should carry a warning for the given member on the linked attendance day.
 *
 * Never blocks claiming: "yes" and "maybe" are both considered available with
 * no warning; an explicit "no" or no response at all surface a warning tag
 * instead, so the claimant can make an informed choice, per the confirmed
 * "warn, don't block" behavior (see IMPLEMENDATION.md Phase 6 deviations).
 */
export function describeAvailability(
    option: PollOption | null | undefined,
    userId: number,
): AvailabilityWarning | null {
    if (!option) {
        return null;
    }

    const status = responseFor(option, userId);

    if (status === null) {
        return {
            label: "You haven't responded to the attendance poll for that day.",
        };
    }

    if (status === 'no') {
        return { label: 'You marked yourself unavailable that day.' };
    }

    return null;
}
