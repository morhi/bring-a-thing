<script setup lang="ts">
import { Link, http } from '@inertiajs/vue3';
import { InfoIcon, XIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import dismissCredentialHint from '@/actions/App/Http/Controllers/CredentialHintController';
import { edit as editSettings } from '@/actions/App/Http/Controllers/SettingsController';
import { useI18n } from '@/composables/use-i18n';

const props = defineProps<{ hint: App.Enums.CredentialHint }>();

const { t } = useI18n();
const hidden = ref(false);

const text = computed(() =>
    props.hint === 'email'
        ? t('credentialHint.email')
        : t('credentialHint.noEmail'),
);

async function dismissHint(): Promise<void> {
    hidden.value = true;

    try {
        await http.getClient().request({
            method: 'post',
            url: dismissCredentialHint.url(),
            headers: { Accept: 'application/json' },
        });
    } catch {
        hidden.value = false;
        toast.error(t('group.requestFailed'), { duration: 6000 });
    }
}
</script>

<template>
    <div
        v-if="!hidden"
        role="region"
        data-feature="F-02"
        :aria-label="t('credentialHint.label')"
        class="bg-info-soft text-info border-info/30 flex items-start justify-center gap-3 border-b px-4 py-2 text-sm sm:items-center"
    >
        <InfoIcon class="mt-2.5 size-4 shrink-0 sm:mt-0" aria-hidden="true" />
        <span class="flex flex-col gap-0.5 py-1.5 sm:flex-row sm:gap-2 sm:py-0">
            <span>{{ text }}</span>
            <Link
                :href="editSettings.url()"
                class="font-medium whitespace-nowrap underline underline-offset-2"
                >{{ t('credentialHint.settings') }}</Link
            >
        </span>
        <button
            type="button"
            class="hover:bg-info/10 focus-visible:ring-ring -my-0.5 -me-2 grid size-9 shrink-0 place-items-center rounded focus-visible:ring-2 focus-visible:outline-none sm:-my-2"
            :aria-label="t('credentialHint.dismiss')"
            @click="dismissHint"
        >
            <XIcon class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
