<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copyText } from '@/clipboard';

const suggestions = [
    'Who should I follow up with today?',
    'Who wanted a pet-friendly place?',
    'What did I promise Elena?',
    'Draft a follow-up email to Andrea about the shortlist I promised',
];

// What each tool means, for the "Checked:" line under an answer.
const toolLabels = {
    get_overdue_followups: 'follow-ups due',
    find_contact: 'contacts',
    get_contact_notes: 'contact notes',
    search_notes: 'searched notes',
    draft_followup_message: 'wrote a draft',
};

// The conversation lives in this tab only (sessionStorage), so a refresh keeps it and closing the tab forgets it.
const STORAGE_KEY = 'metmemo.assistant.chat';
const restore = () => {
    try {
        return JSON.parse(sessionStorage.getItem(STORAGE_KEY)) ?? [];
    } catch {
        return [];
    }
};

const messages = ref(restore());
const draft = ref('');
const sending = ref(false);
const thread = ref(null);
const input = ref(null);

watch(messages, (value) => {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Private mode or storage full: the chat still works, it just won't survive a refresh.
    }
}, { deep: true });

// Earlier turns sent as the assistant's memory (the server accepts up to 20).
const history = computed(() => messages.value
    .filter((message) => ! message.error)
    .map((message) => ({ role: message.role, content: message.content }))
    .slice(-20));

const scrollToEnd = async () => {
    await nextTick();
    thread.value?.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const ask = async (text = draft.value) => {
    const message = text.trim();
    if (! message || sending.value) return;

    const memory = history.value;
    messages.value.push({ role: 'user', content: message });
    draft.value = '';
    sending.value = true;
    scrollToEnd();

    try {
        const { data } = await window.axios.post(route('assistant.ask'), { message, history: memory });
        messages.value.push({
            role: 'assistant',
            content: data.answer,
            tools: data.tool_calls,
            drafts: data.drafts,
            sources: data.sources,
        });
    } catch (error) {
        messages.value.push({
            role: 'assistant',
            error: true,
            retry: message,
            content: error.response?.status === 429
                ? 'That was a lot of questions in a minute. Wait a moment and try again.'
                : error.response?.data?.message
                    ?? error.response?.data?.errors?.message?.[0]
                    ?? 'The assistant is unavailable right now. Try again in a minute.',
        });
    } finally {
        sending.value = false;
        scrollToEnd();
        nextTick(() => input.value?.focus());
    }
};

// A failed turn sits right after its question: drop both, then ask again.
const retry = (index) => {
    const question = messages.value[index].retry;
    messages.value.splice(index - 1, 2);
    ask(question);
};

const newChat = () => {
    messages.value = [];
    draft.value = '';
    nextTick(() => input.value?.focus());
};

const onKeydown = (event) => {
    if (event.key === 'Enter' && ! event.shiftKey && ! event.isComposing) {
        event.preventDefault();
        ask();
    }
};

// --- Rendering answers ------------------------------------------------------------------------
// Answers can quote notes written by website visitors, so they are never rendered as HTML.
// Instead each line is split into plain text, **bold** and [N1] citation tokens, rendered as elements.
const TOKEN = /(\*\*[^*]+\*\*|\[N\d+\])/g;
const LIST_ITEM = /^\s*(?:(\d+)\.|[-*•])\s+/;

const blocks = (text) => (text ?? '').split('\n').map((line) => line.trimEnd()).filter((line, i, all) => line || (all[i - 1] && all[i + 1]))
    .map((line) => {
        const item = line.match(LIST_ITEM);
        const body = item ? line.slice(item[0].length) : line;
        const tokens = body.split(TOKEN).filter(Boolean).map((part) => {
            if (/^\*\*[^*]+\*\*$/.test(part)) return { type: 'bold', text: part.slice(2, -2) };
            if (/^\[N\d+\]$/.test(part)) return { type: 'cite', text: part.slice(1, -1) };
            return { type: 'text', text: part };
        });
        return { item: item ? (item[1] ? `${item[1]}.` : '•') : null, tokens, blank: ! line.trim() };
    });

const openSources = ref({});
const showSource = async (index, noteRef) => {
    openSources.value[index] = true;
    await nextTick();
    document.getElementById(`source-${index}-${noteRef}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const copyDraft = (item) => copyText(item.subject ? `Subject: ${item.subject}\n\n${item.body}` : item.body, 'Draft copied');

const noteDate = (iso) => new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

const checked = (tools) => [...new Set((tools ?? []).map((name) => toolLabels[name] ?? name))].join(', ');
</script>

<template>
    <AppLayout title="Assistant">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assistant</h2>
                    <p class="text-sm text-gray-500 mt-1">Ask about your contacts and notes. It drafts follow-ups; you send them.</p>
                </div>
                <button
                    v-if="messages.length"
                    type="button"
                    class="shrink-0 text-sm text-indigo-600 hover:underline disabled:opacity-50"
                    :disabled="sending"
                    @click="newChat"
                >
                    New chat
                </button>
            </div>
        </template>

        <div class="page max-w-3xl py-6 sm:py-8">
            <!-- Empty state -->
            <section v-if="! messages.length" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="font-semibold text-gray-900">What do you need to remember?</h3>
                        <p class="text-sm text-gray-500">It reads your contacts and meeting notes, and cites the notes it used.</p>
                    </div>
                </div>
                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        class="rounded-xl border border-gray-200 px-4 py-3 text-start text-sm text-gray-700 hover:border-indigo-500 hover:bg-indigo-50/40 transition"
                        @click="ask(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>
                <p class="mt-6 rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-500">
                    The assistant never sends messages or changes your contacts. Drafts are for you to review, copy and send yourself.
                </p>
            </section>

            <!-- Conversation -->
            <ol v-else ref="thread" class="space-y-6" aria-live="polite">
                <li v-for="(message, index) in messages" :key="index" class="scroll-mt-24">
                    <!-- You -->
                    <div v-if="message.role === 'user'" class="flex justify-end">
                        <p class="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-accent text-accent-fg px-4 py-2.5 text-sm">{{ message.content }}</p>
                    </div>

                    <!-- Failed turn -->
                    <div v-else-if="message.error" class="rounded-xl border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 px-4 py-3 text-sm flex items-start justify-between gap-4">
                        <span>{{ message.content }}</span>
                        <button type="button" class="shrink-0 font-medium underline disabled:opacity-50" :disabled="sending" @click="retry(index)">Try again</button>
                    </div>

                    <!-- Assistant -->
                    <div v-else class="space-y-3">
                        <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-md px-4 py-3 sm:px-5 sm:py-4 text-sm text-gray-800 space-y-1.5">
                            <template v-for="(block, b) in blocks(message.content)" :key="b">
                                <div v-if="block.blank" class="h-1" />
                                <p v-else :class="block.item ? 'flex gap-2 ps-1' : ''">
                                    <span v-if="block.item" class="shrink-0 text-gray-400 tabular-nums">{{ block.item }}</span>
                                    <span>
                                        <template v-for="(token, t) in block.tokens" :key="t">
                                            <strong v-if="token.type === 'bold'" class="font-semibold text-gray-900">{{ token.text }}</strong>
                                            <button
                                                v-else-if="token.type === 'cite' && message.sources?.some((s) => s.ref === token.text)"
                                                type="button"
                                                class="mx-0.5 inline-flex items-center rounded-md bg-indigo-50 px-1.5 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-100 align-[1px]"
                                                :title="`Show note ${token.text}`"
                                                @click="showSource(index, token.text)"
                                            >{{ token.text }}</button>
                                            <template v-else-if="token.type === 'cite'" />
                                            <template v-else>{{ token.text }}</template>
                                        </template>
                                    </span>
                                </p>
                            </template>
                            <p v-if="message.tools?.length" class="pt-2 text-xs text-gray-400">Checked: {{ checked(message.tools) }}</p>
                        </div>

                        <!-- Drafts -->
                        <article
                            v-for="(item, d) in message.drafts"
                            :key="d"
                            class="bg-white border border-gray-200 rounded-2xl overflow-hidden"
                        >
                            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                                <div class="min-w-0 text-sm">
                                    <span class="font-semibold text-gray-900">Draft to {{ item.contact_name }}</span>
                                    <span class="ms-2 rounded-full bg-white border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-500">{{ item.channel === 'text' ? 'Text message' : 'Email' }}</span>
                                </div>
                                <span class="text-[11px] font-medium text-gray-500">Not sent</span>
                            </header>
                            <div class="px-4 py-3 text-sm text-gray-800">
                                <p v-if="item.subject" class="mb-2"><span class="text-gray-500">Subject:</span> {{ item.subject }}</p>
                                <p class="whitespace-pre-wrap">{{ item.body }}</p>
                            </div>
                            <footer class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-200 px-4 py-2.5">
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-accent text-accent-fg px-3 py-1.5 text-xs font-semibold hover:opacity-90" @click="copyDraft(item)">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                    </svg>
                                    Copy
                                </button>
                                <Link v-if="item.url" :href="item.url" class="text-xs font-medium text-indigo-600 hover:underline">Open contact</Link>
                                <span v-if="item.sources?.length" class="text-xs text-gray-500">
                                    Based on
                                    <button v-for="noteRef in item.sources" :key="noteRef" type="button" class="mx-0.5 rounded-md bg-indigo-50 px-1.5 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-100" @click="showSource(index, noteRef)">{{ noteRef }}</button>
                                </span>
                                <span v-else class="text-xs text-amber-600">Not based on any note: check it carefully.</span>
                            </footer>
                        </article>

                        <!-- Cited notes -->
                        <details
                            v-if="message.sources?.length"
                            class="group rounded-2xl border border-gray-200 bg-white"
                            :open="openSources[index]"
                            @toggle="openSources[index] = $event.target.open"
                        >
                            <summary class="cursor-pointer list-none px-4 py-2.5 text-xs font-medium text-gray-500 hover:text-gray-800 flex items-center gap-2">
                                <svg class="size-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                                {{ message.sources.length }} {{ message.sources.length === 1 ? 'note' : 'notes' }} used
                            </summary>
                            <ul class="divide-y divide-gray-200 border-t border-gray-200">
                                <li v-for="source in message.sources" :id="`source-${index}-${source.ref}`" :key="source.ref" class="flex gap-3 px-4 py-3 text-sm">
                                    <span class="mt-0.5 h-fit rounded-md bg-indigo-50 px-1.5 text-[11px] font-semibold text-indigo-600">{{ source.ref }}</span>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-500">
                                            <Link v-if="source.url" :href="source.url" class="font-medium text-gray-800 hover:text-indigo-600">{{ source.contact_name }}</Link>
                                            <span v-else class="font-medium text-gray-800">{{ source.contact_name }}</span>
                                            · {{ noteDate(source.created_at) }}
                                        </p>
                                        <p class="mt-1 whitespace-pre-wrap text-gray-700">{{ source.body }}</p>
                                    </div>
                                </li>
                            </ul>
                        </details>
                    </div>
                </li>

                <!-- Thinking -->
                <li v-if="sending" class="flex items-center gap-3 text-sm text-gray-500">
                    <svg class="size-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    Checking your contacts and notes…
                </li>
            </ol>

            <!-- Composer: stays above the phone tab bar -->
            <form class="sticky bottom-20 lg:bottom-4 mt-6 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500" @submit.prevent="ask()">
                <div class="flex items-end gap-2">
                    <label for="assistant-message" class="sr-only">Ask the assistant</label>
                    <textarea
                        id="assistant-message"
                        ref="input"
                        v-model="draft"
                        rows="1"
                        maxlength="2000"
                        placeholder="Ask about a contact, or who to follow up with…"
                        class="block max-h-40 min-h-[2.5rem] w-full resize-none border-0 bg-transparent px-2 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 [field-sizing:content]"
                        @keydown="onKeydown"
                    />
                    <button
                        type="submit"
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-fg transition hover:opacity-90 disabled:opacity-40"
                        :disabled="sending || ! draft.trim()"
                        aria-label="Send"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </button>
                </div>
            </form>
            <p class="mt-2 text-center text-[11px] text-gray-400">The assistant can make mistakes. Check drafts before you send them.</p>
        </div>
    </AppLayout>
</template>
