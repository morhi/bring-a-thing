<script lang="ts">
export type ItemRowItem = {
    id: number | string;
    name: string;
    quantity: number | null;
    unit?: string | null;
    notes?: string | null;
    fields?: { name: string; value: string }[];
    // Attendance day of the item itself (F-18); the list's own day is shown in the list head.
    attendanceDay?: { label: string; href: string } | null;
};
</script>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarCheck, Ellipsis, MessageSquare } from '@lucide/vue';
import { computed, useId } from 'vue';
import type {
    ClaimCandidate,
    ClaimEntry,
    ClaimId,
    ClaimRequest,
} from '@/components/app/claim-controls.vue';
import ClaimControls from '@/components/app/claim-controls.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        item: ItemRowItem;
        claims: ClaimEntry[];
        canClaim?: boolean;
        locked?: boolean;
        candidates?: ClaimCandidate[];
        attendanceWarning?: string | null;
        error?: string | null;
        processing?: boolean;
        commentCount?: number;
        canComment?: boolean;
        canEdit?: boolean;
    }>(),
    {
        canClaim: true,
        locked: false,
        candidates: () => [],
        attendanceWarning: null,
        error: null,
        processing: false,
        commentCount: 0,
        canComment: false,
        canEdit: false,
    },
);

const emit = defineEmits<{
    claim: [request: ClaimRequest];
    release: [claimId: ClaimId];
    edit: [];
    delete: [];
}>();

const commentsOpen = defineModel<boolean>('commentsOpen', { default: false });

const { t, locale } = useI18n();

const threadId = useId();

function formatAmount(value: number): string {
    const number = value.toLocaleString(
        locale.value === 'de' ? 'de-CH' : 'en-GB',
    );

    return props.item.unit ? `${number} ${props.item.unit}` : number;
}

const remaining = computed(() => {
    if (props.item.quantity === null) {
        return props.claims.length ? 0 : 1;
    }

    const claimed = props.claims.reduce(
        (sum, claim) => sum + (claim.quantity ?? 0),
        0,
    );

    return Math.max(0, props.item.quantity - claimed);
});

const done = computed(() => remaining.value === 0);

const statusLabel = computed(() => {
    if (props.item.quantity === null) {
        return done.value ? t('list.item.taken') : t('list.item.open');
    }

    return done.value
        ? t('list.item.complete')
        : t('list.item.missing', { n: formatAmount(remaining.value) });
});

const showCommentToggle = computed(
    () => props.canComment && (props.commentCount > 0 || commentsOpen.value),
);
const showAddComment = computed(
    () => props.canComment && !showCommentToggle.value,
);
const hasMenu = computed(() => showAddComment.value || props.canEdit);
</script>

<template>
    <!-- The surrounding SectionBoxItem draws the box, so the row stays plain inside any container -->
    <article
        class="flex min-w-0 flex-col gap-3.5"
        :aria-labelledby="`${threadId}-name`"
        data-feature="F-14"
    >
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
            <div class="flex min-w-0 flex-[1_1_240px] flex-col gap-1.5">
                <h3
                    :id="`${threadId}-name`"
                    class="flex flex-wrap items-baseline gap-2.5"
                >
                    <span
                        v-if="item.quantity === null"
                        class="text-fg-soft font-sans text-xs font-semibold tracking-wider whitespace-nowrap uppercase"
                    >
                        {{ t('list.item.task') }}
                    </span>
                    <span
                        v-else
                        class="text-primary text-[15px] font-bold whitespace-nowrap tabular-nums"
                        >{{ formatAmount(item.quantity) }}</span
                    >
                    <span
                        :class="
                            cn(
                                'min-w-0 text-lg break-words',
                                done && 'text-fg-muted',
                            )
                        "
                        >{{ item.name }}</span
                    >
                </h3>
                <p
                    v-if="item.notes"
                    class="text-fg-muted text-[15px] break-words"
                >
                    {{ item.notes }}
                </p>
                <dl
                    v-if="item.fields?.length"
                    class="m-0 flex flex-wrap gap-x-4 gap-y-1.5 text-sm"
                    data-feature="F-16"
                >
                    <div
                        v-for="field in item.fields"
                        :key="field.name"
                        class="flex gap-1.5"
                    >
                        <dt class="text-fg-soft after:content-[':']">
                            {{ field.name }}
                        </dt>
                        <dd class="m-0 break-words">{{ field.value }}</dd>
                    </div>
                </dl>
                <Link
                    v-if="item.attendanceDay"
                    :href="item.attendanceDay.href"
                    class="text-primary hover:text-primary-hover inline-flex w-fit items-center gap-1.5 text-sm font-semibold hover:underline"
                    data-feature="F-18"
                >
                    <CalendarCheck class="size-4" aria-hidden="true" />
                    {{
                        t('list.attendance.day', {
                            date: item.attendanceDay.label,
                        })
                    }}
                </Link>
            </div>
            <span
                :class="
                    cn(
                        'flex-none text-sm font-semibold whitespace-nowrap',
                        done ? 'text-success' : 'text-warning',
                    )
                "
                >{{ statusLabel }}</span
            >
        </div>

        <ClaimControls
            :quantity="item.quantity"
            :unit="item.unit ?? null"
            :claims="claims"
            :can-claim="canClaim"
            :locked="locked"
            :candidates="candidates"
            :attendance-warning="attendanceWarning"
            :error="error"
            :processing="processing"
            @claim="emit('claim', $event)"
            @release="emit('release', $event)"
        >
            <template #actions>
                <Button
                    v-if="showCommentToggle"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="tabular-nums"
                    :aria-expanded="commentsOpen"
                    :aria-controls="threadId"
                    :aria-label="t('list.comments.count', { n: commentCount })"
                    data-feature="F-20"
                    @click="commentsOpen = !commentsOpen"
                >
                    <MessageSquare aria-hidden="true" />
                    {{ commentCount }}
                </Button>
                <DropdownMenu v-if="hasMenu">
                    <DropdownMenuTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="t('list.item.more')"
                        >
                            <Ellipsis />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-52">
                        <DropdownMenuItem
                            v-if="showAddComment"
                            data-feature="F-20"
                            @select="commentsOpen = true"
                            >{{ t('list.comments.add') }}</DropdownMenuItem
                        >
                        <DropdownMenuSeparator
                            v-if="showAddComment && canEdit"
                        />
                        <DropdownMenuItem
                            v-if="canEdit"
                            @select="emit('edit')"
                            >{{ t('list.item.edit') }}</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="canEdit"
                            variant="destructive"
                            @select="emit('delete')"
                            >{{ t('list.item.delete') }}</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </ClaimControls>

        <div
            v-if="commentsOpen"
            :id="threadId"
            class="border-border border-t pt-3.5"
            data-feature="F-20"
        >
            <slot name="comments" />
        </div>
    </article>
</template>
