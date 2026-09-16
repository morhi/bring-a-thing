<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Users from '@primeicons/vue/users';
import ListCheck from '@primeicons/vue/list-check';
import Sync from '@primeicons/vue/sync';
import Calendar from '@primeicons/vue/calendar';
import Comments from '@primeicons/vue/comments';
import Bell from '@primeicons/vue/bell';
import LockOpen from '@primeicons/vue/lock-open';
import Ban from '@primeicons/vue/ban';
import ShoppingBag from '@primeicons/vue/shopping-bag';
import Home from '@primeicons/vue/home';
import Map from '@primeicons/vue/map';
import Gift from '@primeicons/vue/gift';
import CartPlus from '@primeicons/vue/cart-plus';
import Box from '@primeicons/vue/box';
import Send from '@primeicons/vue/send';
import CheckCircle from '@primeicons/vue/check-circle';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import OnboardingForm from '@/components/OnboardingForm.vue';
import type { Auth } from '@/types';

defineOptions({ layout: WebsiteLayout });

const page = usePage<{ auth: Auth }>();

const useCases = [
    {
        icon: ShoppingBag,
        title: 'Potlucks & dinner parties',
        description:
            "Add every dish to a shared list and let members claim what they're bringing, so you don't end up with five salads and no dessert.",
    },
    {
        icon: Home,
        title: 'Weekly meal plans',
        description:
            'Plan "Meal Monday", "Meal Tuesday", and so on as separate items, each pinned to its own day, in a single reusable list.',
    },
    {
        icon: Map,
        title: 'Camping trips & shared stays',
        description:
            'Split gear, food, and supplies across everyone going, with quantities so nobody packs three tents and nobody packs none.',
    },
    {
        icon: CartPlus,
        title: 'House parties & shared purchases',
        description:
            'Track drinks, snacks, and equipment that a group is chipping in on, including items several people split the cost or quantity of.',
    },
    {
        icon: Gift,
        title: 'Group gifts & wish lists',
        description:
            'Coordinate a shared gift or a wish list for an occasion without spoiling the surprise in a group chat everyone can read.',
    },
    {
        icon: Box,
        title: 'Recurring household lists',
        description:
            'Duplicate a list you already set up, like a shared grocery run or a supply list for a regular household event, instead of rebuilding it each time.',
    },
];

const features = [
    {
        icon: Users,
        title: 'Groups & lists',
        description:
            'Create a group for a household, a team, or a circle of friends, and attach any number of lists to it. Lists can stand entirely on their own too, no group required.',
        available: true,
    },
    {
        icon: ListCheck,
        title: 'Flexible items & custom fields',
        description:
            'Add items with quantity, unit, and notes, then define custom fields (allergens, color, whatever fits) per list. Built for meal plans, birthdays, house parties, and similar shared-item lists.',
        available: true,
    },
    {
        icon: Sync,
        title: 'Split claiming',
        description:
            'A single item, like "10 chairs" or "2 kg potatoes", can be claimed by several members at once, each bringing their own share, instead of forcing one person to cover the whole thing.',
        available: true,
    },
    {
        icon: Bell,
        title: 'Live claim updates',
        description:
            'Claims and changes appear instantly for everyone viewing a list, over real-time WebSockets. No refreshing to check who already brought what.',
        available: true,
    },
    {
        icon: LockOpen,
        title: 'Passwordless sign-in',
        description:
            'Register with just an email and sign in with a magic link. Invited members get instant access without a forced account-creation step.',
        available: true,
    },
    {
        icon: Calendar,
        title: 'Scheduling polls',
        description:
            'Propose candidate dates and let members vote yes, no, or maybe to find a date that works, or track ongoing day-by-day attendance for a longer group stay.',
        available: false,
    },
    {
        icon: Comments,
        title: 'Comments on lists & items',
        description:
            'Discuss a list, or a single item, in its own short thread, so questions like "can I bring a gluten-free version instead?" stay next to the item they concern.',
        available: false,
    },
    {
        icon: Bell,
        title: 'In-app notification center',
        description:
            'A running feed of invites, comments, and claims relevant to you, updated live, so you don’t have to recheck every list by hand.',
        available: false,
    },
];

const steps = [
    {
        title: 'Create a list',
        description:
            'Name it, optionally attach it to a group, and add items with a quantity, a unit, and notes. Add custom fields if your event needs them.',
    },
    {
        title: 'Invite your group',
        description:
            'Add members by email. They get their own magic link and can view and claim items right away, no account-creation flow required.',
    },
    {
        title: 'Claim & track live',
        description:
            'Members claim whole items or a share of one. Every claim shows up for the whole group instantly, so the list stays accurate without anyone asking around.',
    },
];

const faqs = [
    {
        question:
            'Is there a free app for organizing who brings what to a potluck or group event?',
        answer: "Yes. Create a list, add the items you need, and invite your group by email. Everyone can see and claim items in real time, and there's no cost or ad-supported catch.",
    },
    {
        question: 'Can more than one person bring part of the same item?',
        answer: 'Yes, split claiming lets several members each claim a portion of one item, such as "10 chairs", specifying how much they personally are bringing.',
    },
    {
        question: 'Do members need to create an account to join a list?',
        answer: "No. Inviting someone by email gives them immediate access with their own magic-link sign-in; there's no separate registration step blocking participation.",
    },
    {
        question: 'Does this app show ads or sell my data?',
        answer: "No. There are no ads, no tracking, and no data sold to third parties. It's a paid-for-by-nobody-but-us tool built to do one job well.",
    },
];
</script>

<template>
    <Head title="Bring A Thing" />

    <div>
        <section class="mx-auto w-full max-w-7xl px-6 pt-20 pb-16 text-center">
            <h1
                class="text-surface-900 dark:text-surface-0 text-4xl font-bold tracking-tight sm:text-5xl"
            >
                Stop losing track of who's bringing what.
            </h1>
            <p
                class="text-surface-600 dark:text-surface-300 mx-auto mt-6 max-w-2xl text-lg"
            >
                {{ page.props.name }} is a focused app for organizing shared
                item lists: potlucks, weekly meal plans, camping trips, house
                parties, and group gifts. Add items, invite your group, and
                watch claims update live, with no ads and no tracking.
            </p>
            <template v-if="page.props.auth.user">
                <div class="mt-8 flex items-center justify-center gap-3">
                    <Link href="/dashboard">
                        <Button label="Go to dashboard" size="large" />
                    </Link>
                </div>
            </template>
            <template v-else>
                <div id="get-started" class="mt-8 scroll-mt-24">
                    <OnboardingForm />
                </div>
                <p class="text-surface-400 dark:text-surface-500 mt-4 text-sm">
                    Already have an account? Enter your email above to log in.
                </p>
            </template>
            <p
                class="text-surface-400 dark:text-surface-500 mx-auto mt-6 max-w-2xl text-xs"
            >
                A free potluck list app, group packing list, and
                who's-bringing-what tracker in one, without ads or account walls
                for the people you invite.
            </p>
        </section>

        <section
            class="border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-900/50 border-y"
        >
            <div class="mx-auto w-full max-w-7xl px-6 py-16">
                <h2
                    class="text-surface-900 dark:text-surface-0 text-center text-2xl font-semibold"
                >
                    Built for the situations where lists get messy
                </h2>
                <p
                    class="text-surface-600 dark:text-surface-300 mx-auto mt-3 max-w-xl text-center"
                >
                    A specialized tool for shared item lists and claims, not a
                    general-purpose event planner or to-do app.
                </p>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="useCase in useCases"
                        :key="useCase.title"
                        class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm"
                    >
                        <component
                            :is="useCase.icon"
                            class="text-primary-500 h-6 w-6"
                        />
                        <h3
                            class="text-surface-900 dark:text-surface-0 mt-4 font-semibold"
                        >
                            {{ useCase.title }}
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 text-sm"
                        >
                            {{ useCase.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-6 py-16">
            <h2
                class="text-surface-900 dark:text-surface-0 text-center text-2xl font-semibold"
            >
                What the app actually does
            </h2>
            <p
                class="text-surface-600 dark:text-surface-300 mx-auto mt-3 max-w-xl text-center"
            >
                A small set of specialized features for shared lists and claims,
                some shipped today, some still on the roadmap.
            </p>
            <div class="mt-12 grid gap-12 sm:grid-cols-2">
                <div>
                    <h3
                        class="text-primary-600 dark:text-primary-400 text-sm font-semibold tracking-wide uppercase"
                    >
                        Available now
                    </h3>
                    <ul
                        class="divide-surface-200 dark:divide-surface-800 mt-4 divide-y"
                    >
                        <li
                            v-for="feature in features.filter(
                                (f) => f.available,
                            )"
                            :key="feature.title"
                            class="flex gap-4 py-4 first:pt-0"
                        >
                            <component
                                :is="feature.icon"
                                class="text-primary-500 mt-1 h-5 w-5 shrink-0"
                            />
                            <div>
                                <h4
                                    class="text-surface-900 dark:text-surface-0 font-semibold"
                                >
                                    {{ feature.title }}
                                </h4>
                                <p
                                    class="text-surface-600 dark:text-surface-300 mt-1 text-sm"
                                >
                                    {{ feature.description }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3
                        class="text-surface-500 dark:text-surface-400 text-sm font-semibold tracking-wide uppercase"
                    >
                        On the roadmap
                    </h3>
                    <ul
                        class="divide-surface-200 dark:divide-surface-800 mt-4 divide-y"
                    >
                        <li
                            v-for="feature in features.filter(
                                (f) => !f.available,
                            )"
                            :key="feature.title"
                            class="flex gap-4 py-4 first:pt-0"
                        >
                            <component
                                :is="feature.icon"
                                class="text-surface-400 dark:text-surface-500 mt-1 h-5 w-5 shrink-0"
                            />
                            <div>
                                <h4
                                    class="text-surface-900 dark:text-surface-0 font-semibold"
                                >
                                    {{ feature.title }}
                                </h4>
                                <p
                                    class="text-surface-600 dark:text-surface-300 mt-1 text-sm"
                                >
                                    {{ feature.description }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section
            class="border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-900/50 border-y"
        >
            <div class="mx-auto w-full max-w-7xl px-6 py-16">
                <h2
                    class="text-surface-900 dark:text-surface-0 text-center text-2xl font-semibold"
                >
                    How it works
                </h2>
                <div class="mt-12 grid gap-8 sm:grid-cols-3">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="flex flex-col items-center text-center"
                    >
                        <div
                            class="bg-primary-500 flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold text-white"
                        >
                            {{ index + 1 }}
                        </div>
                        <h3
                            class="text-surface-900 dark:text-surface-0 mt-4 font-semibold"
                        >
                            {{ step.title }}
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 max-w-xs text-sm"
                        >
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-6 py-16">
            <div class="grid gap-10 sm:grid-cols-2">
                <div class="flex gap-4">
                    <LockOpen class="text-primary-500 h-6 w-6 shrink-0" />
                    <div>
                        <h3
                            class="text-surface-900 dark:text-surface-0 font-semibold"
                        >
                            Passwordless by default
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 text-sm"
                        >
                            Register with just an email and sign in with a magic
                            link. Set a password later in account settings if
                            you prefer it, entirely optional.
                        </p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <Ban class="text-primary-500 h-6 w-6 shrink-0" />
                    <div>
                        <h3
                            class="text-surface-900 dark:text-surface-0 font-semibold"
                        >
                            No ads, ever
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 text-sm"
                        >
                            {{ page.props.name }} isn't funded by ads or by
                            selling attention. It's a tool for organizing your
                            group's shared lists, nothing else competing for
                            space on the page.
                        </p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <Send class="text-primary-500 h-6 w-6 shrink-0" />
                    <div>
                        <h3
                            class="text-surface-900 dark:text-surface-0 font-semibold"
                        >
                            No forced sign-up for invitees
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 text-sm"
                        >
                            Invite someone by email and they get access right
                            away with their own magic link, instead of being
                            stopped by a registration form before they can claim
                            an item.
                        </p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <CheckCircle class="text-primary-500 h-6 w-6 shrink-0" />
                    <div>
                        <h3
                            class="text-surface-900 dark:text-surface-0 font-semibold"
                        >
                            Built for one job
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 text-sm"
                        >
                            {{ page.props.name }} does shared item lists and
                            claims well, rather than trying to be a full
                            event-planning suite with budgets, seating charts,
                            or invitations built in.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-surface-200 dark:border-surface-800 border-t">
            <div class="mx-auto w-full max-w-7xl px-6 py-16">
                <h2
                    class="text-surface-900 dark:text-surface-0 text-center text-2xl font-semibold"
                >
                    Common questions
                </h2>
                <div class="mx-auto mt-10 flex max-w-2xl flex-col gap-6">
                    <div v-for="faq in faqs" :key="faq.question">
                        <h3
                            class="text-surface-900 dark:text-surface-0 font-semibold"
                        >
                            {{ faq.question }}
                        </h3>
                        <p
                            class="text-surface-600 dark:text-surface-300 mt-2 text-sm"
                        >
                            {{ faq.answer }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-surface-200 dark:border-surface-800 border-t">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col items-center gap-4 px-6 py-16 text-center"
            >
                <h2
                    class="text-surface-900 dark:text-surface-0 text-2xl font-semibold"
                >
                    Ready to stop chasing people in the group chat?
                </h2>
                <template v-if="page.props.auth.user">
                    <Link href="/dashboard">
                        <Button label="Go to dashboard" size="large" />
                    </Link>
                </template>
                <template v-else>
                    <a href="#get-started">
                        <Button label="Create your first list" size="large" />
                    </a>
                </template>
            </div>
        </section>
    </div>
</template>
