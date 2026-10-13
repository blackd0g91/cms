<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CpIcon from '@/components/cp/CpIcon.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { store } from '@/routes/cp/notifications';

defineOptions({ layout: CpLayout });

type Recipient = {
    id: number;
    name: string;
    email: string;
    browsers: number;
};

type Sent = {
    id: number;
    to_everyone: boolean;
    title: string;
    body: string;
    url: string;
    people: number;
    reached: number;
    failed: number;
    sender: string | null;
    recipient: string | null;
    sent_at: string;
};

const props = defineProps<{
    // Whether the push keys are set (see config/push.php).
    enabled: boolean;
    // The site's logo as a square, which shows beside the message.
    icon: string;
    // Everyone with notifications on in at least one browser.
    recipients: Recipient[];
    sent: Sent[];
}>();

const page = usePage();

const form = useForm({
    to: 'everyone',
    title: page.props.name,
    body: '',
    url: '',
});

const browsers = computed(() =>
    props.recipients.reduce((sum, recipient) => sum + recipient.browsers, 0),
);

const plural = (count: number, one: string, many: string) =>
    `${count} ${count === 1 ? one : many}`;

const send = () => {
    if (
        form.to === 'everyone' &&
        !confirm(
            `Send this to ${plural(props.recipients.length, 'person', 'people')}, on ${plural(browsers.value, 'browser', 'browsers')}?`,
        )
    ) {
        return;
    }

    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('body', 'url'),
    });
};

// How the last one went, until dismissed.
const result = ref<{ people: number; reached: number; failed: number } | null>(
    null,
);

watch(
    () => page.flash.pushResult,
    (value) => {
        if (value) {
            result.value = value;
        }
    },
    { immediate: true },
);

const formatDate = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Head title="Notifications" />
    <PageHeader title="Notifications" />

    <p class="mb-6 max-w-3xl text-sm text-neutral-500">
        Send a push notification with a message of your own. It reaches the
        people who turned notifications on in a browser, from the reminders on
        the site, even with the site closed.
    </p>

    <section
        v-if="!enabled"
        class="mb-6 max-w-3xl rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
    >
        Push notifications are off: run
        <code class="font-mono">php artisan app:push-keys</code> on the server
        and put the two keys it prints in <code class="font-mono">.env</code>.
    </section>

    <section
        v-if="result"
        role="status"
        :class="[
            'mb-6 flex max-w-3xl items-start justify-between gap-3 rounded-lg border p-4 text-sm',
            result.failed
                ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100'
                : 'border-green-300 bg-green-50 text-green-950 dark:border-green-900 dark:bg-green-950 dark:text-green-100',
        ]"
    >
        <p>
            Sent to {{ plural(result.people, 'person', 'people') }}:
            {{ plural(result.reached, 'browser', 'browsers') }} took it<template
                v-if="result.failed"
                >, {{ result.failed }} refused it (see the log)</template
            >.
        </p>
        <button type="button" class="text-xs underline" @click="result = null">
            Dismiss
        </button>
    </section>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <form class="cp-card space-y-4 p-5" @submit.prevent="send">
            <div>
                <label for="push-to" class="cp-label">To</label>
                <select
                    id="push-to"
                    v-model="form.to"
                    class="cp-input mt-1.5"
                    :disabled="recipients.length === 0"
                >
                    <option value="everyone">
                        Everyone with notifications on ({{
                            plural(recipients.length, 'person', 'people')
                        }})
                    </option>
                    <option
                        v-for="recipient in recipients"
                        :key="recipient.id"
                        :value="String(recipient.id)"
                    >
                        {{ recipient.name }} ({{ recipient.email }}) ·
                        {{ plural(recipient.browsers, 'browser', 'browsers') }}
                    </option>
                </select>
                <p v-if="form.errors.to" class="cp-error mt-1">
                    {{ form.errors.to }}
                </p>
            </div>

            <div>
                <label for="push-title" class="cp-label">Title</label>
                <input
                    id="push-title"
                    v-model="form.title"
                    type="text"
                    required
                    maxlength="60"
                    class="cp-input mt-1.5"
                />
                <p v-if="form.errors.title" class="cp-error mt-1">
                    {{ form.errors.title }}
                </p>
            </div>

            <div>
                <div class="flex items-baseline justify-between">
                    <label for="push-body" class="cp-label">Message</label>
                    <span class="text-xs text-neutral-500 tabular-nums"
                        >{{ form.body.length }}/200</span
                    >
                </div>
                <textarea
                    id="push-body"
                    v-model="form.body"
                    required
                    maxlength="200"
                    rows="3"
                    class="cp-input mt-1.5"
                    placeholder="A new recipe is up: pão de queijo."
                />
                <p v-if="form.errors.body" class="cp-error mt-1">
                    {{ form.errors.body }}
                </p>
            </div>

            <div>
                <label for="push-url" class="cp-label">Opens</label>
                <input
                    id="push-url"
                    v-model="form.url"
                    type="text"
                    maxlength="255"
                    class="cp-input mt-1.5 font-mono text-xs"
                    placeholder="/"
                />
                <p v-if="form.errors.url" class="cp-error mt-1">
                    {{ form.errors.url }}
                </p>
                <p v-else class="mt-1 text-xs text-neutral-500">
                    The page on this site a click on the notification opens,
                    like /recipes. The home page if left empty.
                </p>
            </div>

            <button
                type="submit"
                class="cp-btn-primary"
                :disabled="
                    !enabled || recipients.length === 0 || form.processing
                "
            >
                <CpIcon name="bell" class="size-4" />
                {{ form.processing ? 'Sending…' : 'Send' }}
            </button>
            <p
                v-if="enabled && recipients.length === 0"
                class="text-xs text-neutral-500"
            >
                Nobody has turned notifications on yet.
            </p>
        </form>

        <!-- Roughly how it shows on a computer. -->
        <aside aria-label="Preview">
            <p
                class="mb-2 text-xs font-medium tracking-wide text-neutral-500 uppercase"
            >
                Preview
            </p>
            <div
                class="flex gap-3 rounded-xl border border-neutral-200 bg-white p-3 shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
            >
                <img :src="icon" alt="" class="size-10 shrink-0 rounded-lg" />
                <div class="min-w-0 text-sm">
                    <p class="truncate font-semibold">
                        {{ form.title || 'Title' }}
                    </p>
                    <p
                        class="line-clamp-3 break-words text-neutral-600 dark:text-neutral-300"
                    >
                        {{ form.body || 'Your message' }}
                    </p>
                </div>
            </div>
        </aside>
    </div>

    <section v-if="sent.length" class="mt-8 max-w-5xl">
        <h2 class="mb-3 font-semibold">Sent</h2>
        <ul class="cp-card divide-y divide-neutral-200 dark:divide-neutral-800">
            <li v-for="message in sent" :key="message.id" class="p-4 text-sm">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <p class="font-medium">{{ message.title }}</p>
                    <p class="text-xs text-neutral-500">
                        {{ formatDate(message.sent_at) }}
                        <template v-if="message.sender">
                            · by {{ message.sender }}</template
                        >
                    </p>
                </div>
                <p class="mt-0.5 text-neutral-600 dark:text-neutral-300">
                    {{ message.body }}
                </p>
                <p class="mt-1.5 text-xs text-neutral-500">
                    To
                    {{
                        message.to_everyone
                            ? `everyone (${plural(message.people, 'person', 'people')})`
                            : (message.recipient ?? 'someone since removed')
                    }}
                    · {{ plural(message.reached, 'browser', 'browsers') }} took
                    it<template v-if="message.failed">
                        · {{ message.failed }} refused</template
                    >
                    · opens <span class="font-mono">{{ message.url }}</span>
                </p>
            </li>
        </ul>
    </section>
</template>
