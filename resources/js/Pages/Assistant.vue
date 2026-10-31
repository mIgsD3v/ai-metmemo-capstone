<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copyText } from '@/clipboard';
import { ask as confirm } from '@/confirm';
import { timeAgo } from '@/dates';

// Each suggestion shows the kind of question it is: due list, topic search, a promise, a draft.
const suggestions = [
    { text: 'Who should I follow up with today?', tone: 'text-amber-600 dark:text-amber-300', icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
    { text: 'Who wanted a pet-friendly place?', tone: 'text-blue-600 dark:text-blue-300', icon: 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z' },
    { text: 'What did I promise Elena?', tone: 'text-violet-600 dark:text-violet-300', icon: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z' },
    { text: 'Draft a follow-up email to Andrea about the shortlist I promised', tone: 'text-indigo-600', icon: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' },
];

// What each tool means, for the "Checked:" line under an answer.
const toolLabels = {
    get_overdue_followups: 'follow-ups due',
    find_contact: 'contacts',
    get_contact_notes: 'contact notes',
    search_notes: 'searched notes',
    draft_followup_message: 'wrote a draft',
};

const props = defineProps({
    conversations: Array, // saved chats, most recent first
    conversation: Object, // the open chat with its messages, or null for a new one
});

// Chats are saved on the server. The memory the AI gets is read there too, from this chat's saved messages.
const messages = ref([...(props.conversation?.messages ?? [])]);
const draft = ref('');
const sending = ref(false);
const thread = ref(null);
const input = ref(null);
const showChats = ref(false); // phones: the chat list is behind a button

// Opening another chat (or "New chat") swaps the messages.
watch(() => props.conversation?.id, () => {
    messages.value = [...(props.conversation?.messages ?? [])];
    openSources.value = {};
    showChats.value = false;
});

const scrollToEnd = async () => {
    await nextTick();
    thread.value?.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const ask = async (text = draft.value) => {
    const message = text.trim();
    if (! message || sending.value) return;

    messages.value.push({ role: 'user', content: message });
    draft.value = '';
    sending.value = true;
    scrollToEnd();

    try {
        const { data } = await window.axios.post(route('assistant.ask'), { message, conversation_id: props.conversation?.id ?? null });
        messages.value.push(data.message);

        // A new chat gets its own address and joins the list; an old one moves to the top.
        const reload = { preserveState: true, preserveScroll: true, only: ['conversations', 'conversation'] };
        if (props.conversation?.id !== data.conversation.id) {
            router.get(route('assistant.conversation', data.conversation.id), {}, { ...reload, replace: true });
        } else {
            router.reload({ ...reload, only: ['conversations'] });
        }
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

const deleteChat = async (chat) => {
    const yes = await confirm({ title: 'Delete this chat?', message: `"${chat.title}" and its drafts will be gone. Your contacts and notes stay.` });
    if (yes) router.delete(route('assistant.destroy', chat.id));
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

// Guardrail events worth telling the user about (PII masking is routine and stays quiet).
const safetyNotice = (events = []) => {
    if (events.some((e) => ['input.prompt_injection', 'input.bulk_pii_request'].includes(e))) return 'Blocked by a safety check: the assistant only helps with follow-ups and never shares contact details in bulk.';
    if (events.some((e) => e.endsWith('.moderation'))) return 'Blocked by a safety check.';
    if (events.includes('note.prompt_injection')) return 'A note contained instructions from someone else. The assistant treated it as text and did not follow it.';
    return null;
};

const checked = (tools) => [...new Set((tools ?? []).map((name) => toolLabels[name] ?? name))].join(', ');

// Saved chats grouped by day, newest first (the server already sorts them).
const chatGroups = computed(() => {
    const dayKey = (date) => date.toDateString();
    const todayKey = dayKey(new Date());
    const yesterdayKey = dayKey(new Date(Date.now() - 86_400_000));
    const groups = [];

    for (const chat of props.conversations) {
        const key = dayKey(new Date(chat.updated_at));
        const label = key === todayKey ? 'Today' : key === yesterdayKey ? 'Yesterday' : 'Earlier';
        if (groups.at(-1)?.label !== label) groups.push({ label, chats: [] });
        groups.at(-1).chats.push(chat);
    }

    return groups;
});

// Opened from elsewhere with a question (the dashboard's "Draft email"): ask it once, then drop it from the URL.
onMounted(() => {
    const question = new URLSearchParams(window.location.search).get('ask');
    if (question && ! props.conversation) {
        window.history.replaceState(window.history.state, '', route('assistant.show'));
        ask(question);
    }
});
</script>

<template>
    <AppLayout title="Assistant">
        <div class="lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:min-h-[calc(100vh-4rem)]">
            <!-- Saved chats: a column on desktop, behind the "Chats" button on phones -->
            <section
                aria-label="Saved chats"
                class="border-b lg:border-b-0 lg:border-e border-gray-200 bg-gray-100 px-3 py-5 lg:block"
                :class="showChats ? 'block' : 'hidden'"
            >
                <div class="lg:sticky lg:top-[5.25rem] flex flex-col gap-4">
                    <Link :href="route('assistant.show')" class="btn-quiet h-10 w-full text-sm">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New chat
                    </Link>
                    <p v-if="! conversations.length" class="px-2.5 text-[13px] text-gray-400">Your chats are saved here, so you can come back to them.</p>
                    <nav v-else class="max-h-[70vh] overflow-y-auto" aria-label="Chats">
                        <div v-for="(group, g) in chatGroups" :key="group.label" :class="g ? 'pt-3.5' : ''">
                            <p class="eyebrow px-2.5 pb-1.5">{{ group.label }}</p>
                            <ul class="space-y-0.5">
                                <li v-for="chat in group.chats" :key="chat.id" class="group relative">
                                    <Link
                                        :href="route('assistant.conversation', chat.id)"
                                        class="block rounded-lg py-2 ps-2.5 pe-9 transition"
                                        :class="chat.id === conversation?.id ? 'bg-accent/10 text-indigo-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                                        :aria-current="chat.id === conversation?.id ? 'page' : undefined"
                                    >
                                        <span class="block truncate text-[13px] font-medium">{{ chat.title }}</span>
                                        <span class="block text-xs text-gray-400">{{ timeAgo(chat.updated_at) }}</span>
                                    </Link>
                                    <button
                                        type="button"
                                        class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-gray-400 hover:bg-gray-200 hover:text-red-600 lg:opacity-0 lg:group-hover:opacity-100 focus:opacity-100"
                                        :aria-label="`Delete chat: ${chat.title}`"
                                        title="Delete chat"
                                        @click="deleteChat(chat)"
                                    >
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>
            </section>

            <div class="flex min-w-0 flex-col px-4 py-6 sm:px-8 sm:py-8 min-h-[calc(100vh-11rem)] lg:min-h-0">
                <!-- Phones: the chat list is behind a button -->
                <div class="mb-4 flex items-center justify-between lg:hidden">
                    <button type="button" class="btn-quiet h-9" :aria-expanded="showChats" @click="showChats = ! showChats">
                        Chats ({{ conversations.length }})
                    </button>
                    <Link v-if="conversation" :href="route('assistant.show')" class="text-sm font-medium text-indigo-600">New chat</Link>
                </div>

                <!-- Empty state -->
                <section v-if="! messages.length" class="flex flex-1 flex-col items-center justify-center gap-7 py-6">
                    <div class="flex max-w-[520px] flex-col items-center gap-3.5 text-center">
                        <span class="flex size-[52px] items-center justify-center rounded-[14px] border border-accent/25 bg-indigo-50 text-indigo-600">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                            </svg>
                        </span>
                        <h1 class="text-[26px] font-bold tracking-[-0.02em] text-gray-900">What do you need to remember?</h1>
                        <p class="text-[15px] leading-relaxed text-gray-500">Ask about anyone you've met. Answers come from your contacts and meeting notes, and show which notes were used.</p>
                    </div>
                    <div class="grid w-full max-w-[720px] gap-2.5 sm:grid-cols-2">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion.text"
                            type="button"
                            class="panel flex items-start gap-3 px-4 py-3.5 text-start text-sm font-medium leading-snug text-gray-800 transition hover:-translate-y-px hover:border-accent/40 hover:bg-gray-50"
                            @click="ask(suggestion.text)"
                        >
                            <svg class="mt-px size-[18px] shrink-0" :class="suggestion.tone" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="suggestion.icon" />
                            </svg>
                            {{ suggestion.text }}
                        </button>
                    </div>
                </section>

                <!-- Conversation -->
            <ol v-else ref="thread" class="mx-auto w-full max-w-3xl space-y-6 py-2" aria-live="polite">
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
                            <p v-if="safetyNotice(message.guardrails)" class="mt-2 flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                <svg class="mt-px size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                                {{ safetyNotice(message.guardrails) }}
                            </p>
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
                <div class="sticky bottom-20 lg:bottom-4 mx-auto mt-6 w-full max-w-[760px] space-y-2.5">
                    <form
                        class="flex flex-col gap-2.5 rounded-2xl border border-gray-300 bg-white py-3 pe-3 ps-4 shadow-[0_8px_30px_rgba(0,0,0,0.12)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.35)] focus-within:border-accent/60"
                        @submit.prevent="ask()"
                    >
                        <label for="assistant-message" class="sr-only">Ask the assistant</label>
                        <textarea
                            id="assistant-message"
                            ref="input"
                            v-model="draft"
                            rows="2"
                            maxlength="2000"
                            placeholder="Ask about a contact, or who to follow up with…"
                            class="block max-h-40 min-h-[3rem] w-full resize-none border-0 bg-transparent p-0 text-[15px] leading-normal text-gray-900 placeholder:text-gray-400 focus:ring-0 [field-sizing:content]"
                            @keydown="onKeydown"
                        />
                        <div class="flex items-center justify-between gap-2.5">
                            <span class="inline-flex h-7 items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 text-xs text-gray-500">
                                <svg class="size-[13px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                Reading {{ $page.props.counts?.contacts ?? 0 }} contacts and notes
                            </span>
                            <button
                                type="submit"
                                class="flex size-[38px] shrink-0 items-center justify-center rounded-[10px] bg-accent text-accent-fg transition hover:brightness-110 disabled:opacity-40"
                                :disabled="sending || ! draft.trim()"
                                aria-label="Send"
                            >
                                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75" />
                                </svg>
                            </button>
                        </div>
                    </form>
                    <p class="text-center text-xs text-gray-400">Drafts only: the assistant never sends messages or edits contacts. Check drafts before you send them.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
