import { http } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { search as searchRoute } from '@/routes';

// Mirrors SearchData::MIN_LENGTH; shorter queries would only return nothing
export const MIN_QUERY_LENGTH = 2;

const DEBOUNCE_MS = 200;

/**
 * Search the records the signed-in account may see for the command palette (F-25).
 *
 * Fetches debounced while typing, only from two letters on. A response
 * that arrives after a newer request started is dropped, so fast typing
 * never shows the results of an older query.
 */
export function useSearch() {
    const query = ref('');
    const sections = ref<App.Data.SearchSectionData[]>([]);
    const loading = ref(false);
    const failed = ref(false);
    let latestRequest = 0;

    const term = computed(() => query.value.trim());
    const active = computed(() => term.value.length >= MIN_QUERY_LENGTH);

    async function fetchResults(value: string): Promise<void> {
        const request = ++latestRequest;

        try {
            const response = await http.getClient().request({
                method: 'get',
                url: searchRoute({ query: { q: value } }).url,
                headers: { Accept: 'application/json' },
            });

            if (request !== latestRequest) {
                return;
            }

            sections.value = (
                JSON.parse(response.data) as App.Data.SearchData
            ).sections;
            failed.value = false;
        } catch {
            if (request !== latestRequest) {
                return;
            }

            sections.value = [];
            failed.value = true;
        } finally {
            if (request === latestRequest) {
                loading.value = false;
            }
        }
    }

    const debouncedFetch = useDebounceFn(fetchResults, DEBOUNCE_MS);

    watch(term, (value) => {
        if (value.length < MIN_QUERY_LENGTH) {
            // Invalidates a request still in flight
            latestRequest++;
            sections.value = [];
            loading.value = false;
            failed.value = false;

            return;
        }

        loading.value = true;
        void debouncedFetch(value);
    });

    function reset(): void {
        query.value = '';
    }

    return { query, term, active, sections, loading, failed, reset };
}
