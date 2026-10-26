<script setup lang="ts">
import { computed, ref } from "vue";
import ClaimBadge from "@/components/app/claim-badge.vue";
import type {
    ClaimEntry,
    ClaimId,
    ClaimRequest,
} from "@/components/app/claim-controls.vue";
import ClaimControls from "@/components/app/claim-controls.vue";
import { useI18n } from "@/composables/use-i18n";

const { t, locale } = useI18n();

const attendanceDate = computed(() =>
    new Intl.DateTimeFormat(locale.value === "de" ? "de-CH" : "en-GB", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(2026, 10, 14)),
);

const others: ClaimEntry[] = [
    {
        id: 1,
        accountId: 11,
        name: "Anna Berger",
        quantity: 4,
        isOwn: false,
        canRemove: false,
    },
    {
        id: 2,
        accountId: 12,
        name: "Ben Carter",
        quantity: 2,
        isOwn: false,
        canRemove: false,
    },
];

const candidates = computed(() => [
    { id: 13, name: "Chiara Rossi" },
    {
        id: 14,
        name: "Dmitri Ivanov",
        warning: t("list.attendance.otherNo", {
            name: "Dmitri Ivanov",
            date: attendanceDate.value,
        }),
    },
]);

const cases = computed<
    {
        label: string;
        props: InstanceType<typeof ClaimControls>["$props"];
    }[]
>(() => [
    {
        label: "Quantity, nothing claimed",
        props: { quantity: 10, unit: "chairs", claims: [] },
    },
    {
        label: "Partly claimed, claim for others (admin)",
        props: {
            quantity: 10,
            unit: "chairs",
            claims: others,
            candidates: candidates.value,
        },
    },
    {
        label: "Own claim (change or remove)",
        props: {
            quantity: 3,
            unit: "kg",
            claims: [
                {
                    id: 3,
                    accountId: 10,
                    name: "Maurice Hildebrandt",
                    quantity: 1.5,
                    isOwn: true,
                    canRemove: true,
                },
                others[0],
            ],
        },
    },
    {
        label: "Complete, owner removes claims of others",
        props: {
            quantity: 6,
            unit: null,
            claims: others.map((claim) => ({ ...claim, canRemove: true })),
        },
    },
    { label: "Task open", props: { quantity: null, claims: [] } },
    {
        label: "Task taken by someone else",
        props: { quantity: null, claims: [{ ...others[1], quantity: null }] },
    },
    {
        label: "Task taken by me",
        props: {
            quantity: null,
            claims: [
                {
                    id: 4,
                    accountId: 10,
                    name: "Maurice Hildebrandt",
                    quantity: null,
                    isOwn: true,
                    canRemove: true,
                },
            ],
        },
    },
    {
        label: "Locked: pending participant (F-15)",
        props: { quantity: 10, unit: "chairs", claims: others, locked: true },
    },
    {
        label: "Attendance warning, claim anyway (F-18)",
        props: {
            quantity: null,
            claims: [],
            attendanceWarning: t("list.attendance.youNo", {
                date: attendanceDate.value,
            }),
        },
    },
    {
        label: "Not allowed to claim (past list)",
        props: { quantity: 10, claims: others, canClaim: false },
    },
    {
        label: "Processing",
        props: {
            quantity: 10,
            unit: "chairs",
            claims: others,
            processing: true,
            candidates: candidates.value,
        },
    },
    {
        label: "Guest view and long names",
        props: {
            quantity: 12,
            unit: "bottles of sparkling apple juice",
            claims: [
                {
                    id: 5,
                    accountId: null,
                    name: null,
                    quantity: 2,
                    isOwn: false,
                    canRemove: false,
                },
                {
                    id: 6,
                    accountId: 15,
                    name: "Maximiliane Theresia von Hohenzollern-Sigmaringen",
                    quantity: 9,
                    isOwn: false,
                    canRemove: false,
                },
            ],
        },
    },
]);

// Interactive sample: claims are applied locally, the server error is simulated for amounts above 5.
const liveClaims = ref<ClaimEntry[]>([
    {
        id: 20,
        accountId: 11,
        name: "Anna Berger",
        quantity: 3,
        isOwn: false,
        canRemove: true,
    },
]);
const liveError = ref<string | null>(null);
let nextId = 100;

function onClaim(request: ClaimRequest) {
    if ((request.quantity ?? 0) > 5) {
        liveError.value = "The quantity may not exceed what is still missing.";

        return;
    }

    liveError.value = null;
    const accountId = request.accountId ?? 10;
    const name =
        request.accountId === null
            ? "Maurice Hildebrandt"
            : (candidates.value.find(
                  (candidate) => candidate.id === request.accountId,
              )?.name ?? null);
    const rest = liveClaims.value.filter(
        (claim) => claim.accountId !== accountId,
    );

    liveClaims.value = [
        ...rest,
        {
            id: nextId++,
            accountId,
            name,
            quantity: request.quantity,
            isOwn: request.accountId === null,
            canRemove: true,
        },
    ];
}

function onRelease(id: ClaimId) {
    liveClaims.value = liveClaims.value.filter((claim) => claim.id !== id);
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="claim-controls" class="text-xl">
            ClaimControls and ClaimBadge
        </h2>

        <div class="box flex flex-col gap-3 p-5">
            <p class="text-fg-muted text-sm font-semibold">
                Interactive (8 drinks, amounts above 5 return a server error)
            </p>
            <ClaimControls
                :quantity="8"
                unit="drinks"
                :claims="liveClaims"
                :candidates="candidates"
                :error="liveError"
                @claim="onClaim"
                @release="onRelease"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div
                v-for="item in cases"
                :key="item.label"
                class="box flex min-w-0 flex-col gap-3 p-5"
            >
                <p class="text-fg-muted text-sm font-semibold">
                    {{ item.label }}
                </p>
                <ClaimControls v-bind="item.props" />
            </div>
        </div>

        <div class="box flex flex-col gap-1 p-5">
            <p class="text-fg-muted text-sm font-semibold">ClaimBadge</p>
            <ClaimBadge name="Anna Berger" :quantity="4" unit="chairs" />
            <ClaimBadge
                name="Maurice Hildebrandt"
                :quantity="1.5"
                unit="kg"
                is-own
                can-remove
            />
            <ClaimBadge name="Ben Carter" can-remove />
            <ClaimBadge :name="null" :quantity="2" />
            <ClaimBadge name="Ben Carter" :quantity="2" can-remove disabled />
        </div>
    </section>
</template>
