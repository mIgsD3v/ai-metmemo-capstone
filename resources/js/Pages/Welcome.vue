<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const showingProductMenu = ref(false);
const showingUseCaseMenu = ref(false);
const showingMobileMenu = ref(false);

const sections = [
    { id: 'how-it-works', label: 'How it works', hint: 'From first hello to follow-up in four steps' },
    { id: 'features', label: 'Features', hint: 'Cards, contacts and the AI assistant' },
    { id: 'use-cases', label: 'Use cases', hint: 'Who MetMemo is for, and how they use it' },
    { id: 'two-sides', label: 'For you and your visitors', hint: 'What each side sees' },
    { id: 'faq', label: 'FAQ', hint: 'Common questions, answered' },
];

const steps = [
    {
        title: 'Create your card',
        body: 'Add your name, role, company, contact details and a short bio. Make as many cards as you have hats to wear.',
    },
    {
        title: 'Share it in a second',
        body: 'Every card gets its own link and QR code. The person you meet scans it with their phone camera. No app to install.',
    },
    {
        title: 'Capture the contact',
        body: 'Visitors save you to their phone with one tap and can leave their details. You can also scan their paper card or add them by hand.',
    },
    {
        title: 'Follow up on time',
        body: 'Each new contact gets a follow-up date. Your dashboard shows who is due, and the AI assistant drafts the message.',
    },
];

const aiFeatures = [
    {
        title: 'Scan',
        body: 'Snap a paper business card. Any layout, blurry or rotated, becomes a saved contact. Unsure fields are flagged for you to check.',
    },
    {
        title: 'Remember',
        body: 'Ask "what did I talk about with Juan?" and get the answer from your own notes on that contact, not a guess. If it is not in your notes, it says so.',
    },
    {
        title: 'Follow up',
        body: 'Ask "who should I follow up with this week?" and get a short list with draft messages. You review and send.',
    },
];

const coreFeatures = [
    {
        title: 'QR code and share link',
        body: 'A clean public page for every card at its own short link.',
        icon: 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z',
    },
    {
        title: 'Save to contacts',
        body: 'Visitors download your vCard and you land in their phone book.',
        icon: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
    },
    {
        title: 'Lead capture form',
        body: 'Visitors leave their name, number and message. It arrives as a contact.',
        icon: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    },
    {
        title: 'Contacts with notes',
        body: 'Every person you meet, where they came from, and what you talked about.',
        icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    },
    {
        title: 'Follow-up reminders',
        body: 'New leads are due two days after you meet, so nobody goes cold.',
        icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    },
    {
        title: 'Dashboard',
        body: 'Your cards, contacts and who is due for follow-up, at a glance.',
        icon: 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z',
    },
];

const sides = [
    {
        label: 'You, the card owner',
        items: [
            'Build one or more cards for each role you have',
            'Show your QR code or send your link',
            'Get every visitor lead as a contact with a follow-up date',
            'Scan paper cards you receive into contacts',
            'Ask the assistant who to follow up with and get drafts',
        ],
    },
    {
        label: 'The people you meet',
        items: [
            'Scan your QR with their phone camera, no app needed',
            'See your role, company and contact details on one page',
            'Save you to their contacts in one tap',
            'Get a follow-up from you while the meeting is still fresh',
            'Leave their details so you can reach back',
        ],
    },
];

const faqs = [
    {
        question: 'Do the people I meet need to install an app or sign up?',
        answer: 'No. Your card is a normal web page. They scan the QR code with their phone camera or open your link, and everything works in the browser.',
    },
    {
        question: 'What is shown on my public card?',
        answer: 'Only what you put on the card: name, role, company, bio and contact details, plus your profile photo if you uploaded one. Your account email and settings are never shown.',
    },
    {
        question: 'Can I hide a card?',
        answer: 'Yes. Set a card to private and its link and QR code stop working until you make it public again.',
    },
    {
        question: 'What happens when a visitor leaves their details?',
        answer: 'They become a contact in your list, linked to the card they came from, with their message saved as a note and a follow-up date two days out.',
    },
    {
        question: 'How does scanning a paper business card work?',
        answer: 'Take a photo of the card. The AI reads the name, title, company, email and phone, flags any field it is unsure about, and you confirm before it is saved.',
    },
    {
        question: 'Does the AI send messages for me?',
        answer: 'No. It suggests who to follow up with and drafts the message. You review it and send it yourself.',
    },
    {
        question: 'Can I have more than one card?',
        answer: 'Yes. Make a separate card for each role or business, each with its own link and QR code.',
    },
    {
        question: 'Why the name "MetMemo"?',
        answer: '"Met" is the people you meet: your card, QR code, lead form and scanned cards. "Memo" is the memory of each meeting: your notes and the AI that remembers the conversation and tells you when to follow up.',
    },
];

const useCases = [
    {
        id: 'expos',
        title: 'Expos and trade shows',
        hint: 'Dozens of people a day at your booth',
        icon: 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z',
        scenario: 'Three days at a booth, a stack of paper cards, and a week later you cannot remember who wanted a quote and who was just collecting freebies.',
        helps: [
            'Put your QR code on the booth so visitors save you and leave their details in seconds.',
            'Scan the paper cards you receive straight into contacts.',
            'Every new lead is due for follow-up two days later, before the expo glow fades.',
        ],
        memo: { name: 'Juan Dela Cruz', where: 'Expo SMX, booth 214', note: 'Wants a quote for 3 branches. Decision by end of month.', due: 'Follow up in 2 days' },
    },
    {
        id: 'sales',
        title: 'Sales and business development',
        hint: 'More than one role, one place for leads',
        icon: 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
        scenario: 'You meet prospects at client visits, events and meetings, and your follow-ups live in five different places.',
        helps: [
            'Make a separate card for each role or product line, each with its own link and QR code.',
            'Every lead is linked to the card it came from, so you know which pitch worked.',
            'The dashboard shows who is due today, so the day starts with a list, not a guess.',
        ],
        memo: { name: 'Carla Mendoza', where: 'Client visit, Makati', note: 'Interested in the enterprise plan. Needs a deck for her boss.', due: 'Due today' },
    },
    {
        id: 'students',
        title: 'Students and job seekers',
        hint: 'Career fairs and networking events',
        icon: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
        scenario: 'You talk to ten recruiters at a career fair, and the thank-you message you meant to send never gets sent.',
        helps: [
            'Share a professional card instead of a paper resume that gets lost.',
            'Scan recruiter cards and note what each company is hiring for.',
            'Get reminded to send a thank-you while they still remember you.',
        ],
        memo: { name: 'Ms. Lim, HR', where: 'University job fair', note: 'Hiring junior devs. Send portfolio link and thank-you note.', due: 'Follow up in 2 days' },
    },
];

const activeUseCase = ref(useCases[0].id);
const currentUseCase = computed(() => useCases.find((useCase) => useCase.id === activeUseCase.value));

const closeMenus = () => {
    showingProductMenu.value = false;
    showingUseCaseMenu.value = false;
    showingMobileMenu.value = false;
};

const openUseCase = (id) => {
    activeUseCase.value = id;
    closeMenus();
};
</script>

<template>
    <Head title="Every meeting, remembered" />

    <div class="min-h-screen bg-page text-gray-800">
        <!-- Header -->
        <header class="sticky top-0 z-40 bg-page/80 backdrop-blur border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <div class="flex items-center gap-8">
                    <Link href="/">
                        <ApplicationMark />
                    </Link>

                    <nav class="hidden md:flex items-center gap-1 text-sm">
                        <!-- Product dropdown -->
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition"
                                :aria-expanded="showingProductMenu"
                                @click="showingProductMenu = ! showingProductMenu"
                            >
                                Product
                                <svg class="size-4 transition-transform" :class="{ 'rotate-180': showingProductMenu }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div v-show="showingProductMenu" class="fixed inset-0 z-40" @click="showingProductMenu = false" />

                            <transition
                                enter-active-class="transition ease-out duration-150"
                                enter-from-class="opacity-0 -translate-y-1"
                                leave-active-class="transition ease-in duration-100"
                                leave-to-class="opacity-0 -translate-y-1"
                            >
                                <div v-show="showingProductMenu" class="absolute start-0 mt-2 w-80 z-50 bg-white border border-gray-200 rounded-xl shadow-lg p-2">
                                    <a
                                        v-for="section in sections"
                                        :key="section.id"
                                        :href="`#${section.id}`"
                                        class="block px-3 py-2.5 rounded-lg hover:bg-gray-50 transition"
                                        @click="closeMenus"
                                    >
                                        <div class="font-medium text-gray-900">{{ section.label }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ section.hint }}</div>
                                    </a>
                                </div>
                            </transition>
                        </div>

                        <!-- Use cases dropdown -->
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition"
                                :aria-expanded="showingUseCaseMenu"
                                @click="showingUseCaseMenu = ! showingUseCaseMenu"
                            >
                                Use cases
                                <svg class="size-4 transition-transform" :class="{ 'rotate-180': showingUseCaseMenu }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div v-show="showingUseCaseMenu" class="fixed inset-0 z-40" @click="showingUseCaseMenu = false" />

                            <transition
                                enter-active-class="transition ease-out duration-150"
                                enter-from-class="opacity-0 -translate-y-1"
                                leave-active-class="transition ease-in duration-100"
                                leave-to-class="opacity-0 -translate-y-1"
                            >
                                <div v-show="showingUseCaseMenu" class="absolute start-0 mt-2 w-96 z-50 bg-white border border-gray-200 rounded-xl shadow-lg p-2">
                                    <a
                                        v-for="useCase in useCases"
                                        :key="useCase.id"
                                        href="#use-cases"
                                        class="flex gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 transition"
                                        @click="openUseCase(useCase.id)"
                                    >
                                        <span class="shrink-0 flex items-center justify-center size-9 rounded-lg bg-indigo-50 text-indigo-600">
                                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="useCase.icon" />
                                            </svg>
                                        </span>
                                        <span>
                                            <span class="block font-medium text-gray-900">{{ useCase.title }}</span>
                                            <span class="block text-xs text-gray-500 mt-0.5">{{ useCase.hint }}</span>
                                        </span>
                                    </a>
                                </div>
                            </transition>
                        </div>

                        <a href="#how-it-works" class="px-3 py-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition">How it works</a>
                        <a href="#faq" class="px-3 py-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition">FAQ</a>
                    </nav>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 text-sm">
                    <ThemeToggle />
                    <template v-if="canLogin">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-accent">Dashboard</Link>
                        <template v-else>
                            <Link :href="route('login')" class="hidden sm:inline-block px-3 py-2 text-gray-600 hover:text-gray-900">Log in</Link>
                            <Link v-if="canRegister" :href="route('register')" class="btn-accent">Get started</Link>
                        </template>
                    </template>

                    <button
                        type="button"
                        class="md:hidden p-2 rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-50 transition"
                        :aria-expanded="showingMobileMenu"
                        aria-label="Menu"
                        @click="showingMobileMenu = ! showingMobileMenu"
                    >
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path v-if="! showingMobileMenu" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div v-show="showingMobileMenu" class="md:hidden border-t border-gray-200 px-4 py-3 space-y-1">
                <a
                    v-for="section in sections"
                    :key="section.id"
                    :href="`#${section.id}`"
                    class="block px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-50"
                    @click="closeMenus"
                >
                    {{ section.label }}
                </a>
                <Link v-if="canLogin && ! $page.props.auth.user" :href="route('login')" class="block px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-50">
                    Log in
                </Link>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-gray-200">
            <div class="absolute inset-0 bg-grid pointer-events-none" aria-hidden="true" />
            <div class="relative max-w-4xl mx-auto px-4 py-24 sm:py-32 text-center">
                <h1 class="text-5xl sm:text-7xl font-bold tracking-tight text-gray-900">
                    Every meeting,<br>remembered.
                </h1>
                <p class="mt-6 text-lg sm:text-xl text-gray-600">
                    MetMemo is a digital business card with an AI assistant that remembers everyone you meet and tells you when to follow up.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link :href="canRegister ? route('register') : route('login')" class="btn-accent text-base px-6 py-3">
                        Create your free card
                    </Link>
                    <a href="#how-it-works" class="inline-flex items-center gap-2 px-6 py-3 rounded-md border border-gray-300 text-base font-medium text-gray-700 hover:bg-white transition">
                        See how it works
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                        </svg>
                    </a>
                </div>
                <p class="mt-6 text-sm text-gray-500">
                    No app to install · Works on any phone · You stay in control of every message
                </p>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="scroll-mt-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">How it works</p>
                <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">From first hello to follow-up</h2>
                <p class="mt-4 text-gray-600">You meet someone, they scan your card, and MetMemo remembers the conversation so it does not end there.</p>
            </div>

            <ol class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <li v-for="(step, i) in steps" :key="step.title" class="relative bg-white border border-gray-200 rounded-2xl p-6">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center size-9 rounded-full bg-indigo-50 text-indigo-600 font-semibold text-sm">
                            {{ i + 1 }}
                        </span>
                        <h3 class="font-semibold text-gray-900">{{ step.title }}</h3>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 leading-relaxed">{{ step.body }}</p>
                </li>
            </ol>
        </section>

        <!-- Features -->
        <section id="features" class="scroll-mt-20 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">Features</p>
                    <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">An assistant that does the remembering</h2>
                    <p class="mt-4 text-gray-600">Three AI helpers on top of a card and contact list that already work on their own.</p>
                </div>

                <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div
                        v-for="(feature, i) in aiFeatures"
                        :key="feature.title"
                        class="bg-white border border-gray-200 rounded-2xl p-8 sm:p-10 flex flex-col"
                    >
                        <h3 class="text-2xl font-semibold text-gray-900">{{ feature.title }}</h3>
                        <p class="mt-4 text-gray-600 leading-relaxed">{{ feature.body }}</p>

                        <div v-if="i === 2" class="mt-8 -mx-8 sm:-mx-10 -mb-8 sm:-mb-10 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                            <div class="flex text-sm border-b border-gray-200">
                                <span class="px-5 py-3 font-medium text-gray-900 border-b-2 border-indigo-500">assistant</span>
                                <span class="px-5 py-3 text-gray-500">tools</span>
                            </div>
                            <pre class="px-5 py-4 font-mono text-xs leading-relaxed text-gray-600 overflow-x-auto"><span class="text-gray-400"># who should I follow up with?</span>
<span class="text-indigo-600">get_overdue_followups</span>(days=7)
<span class="text-indigo-600">get_contact_notes</span>(id=42)
<span class="text-indigo-600">draft_followup_message</span>(
  contact=<span class="text-amber-600">"Juan, Expo SMX"</span>
)</pre>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div v-for="feature in coreFeatures" :key="feature.title" class="flex gap-4 bg-white border border-gray-200 rounded-2xl p-6">
                        <div class="shrink-0 flex items-center justify-center size-10 rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ feature.title }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ feature.body }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Use cases -->
        <section id="use-cases" class="scroll-mt-20 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">Use cases</p>
                    <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">Made for people who meet a lot of people</h2>
                    <p class="mt-4 text-gray-600">Pick the one that sounds like you.</p>
                </div>

                <div class="mt-12 grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Use case tabs: a scrolling row on phones, a list on large screens -->
                    <div class="flex lg:flex-col gap-2 overflow-x-auto -mx-4 px-4 lg:mx-0 lg:px-0 pb-2 lg:pb-0" role="tablist">
                        <button
                            v-for="useCase in useCases"
                            :key="useCase.id"
                            type="button"
                            role="tab"
                            :aria-selected="activeUseCase === useCase.id"
                            class="shrink-0 flex items-center gap-3 px-4 py-3 rounded-xl border text-start text-sm font-medium transition"
                            :class="activeUseCase === useCase.id
                                ? 'bg-white border-indigo-500 text-gray-900'
                                : 'border-gray-200 text-gray-600 hover:bg-white hover:text-gray-900'"
                            @click="activeUseCase = useCase.id"
                        >
                            <svg class="size-5 shrink-0" :class="activeUseCase === useCase.id ? 'text-indigo-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="useCase.icon" />
                            </svg>
                            <span class="whitespace-nowrap lg:whitespace-normal">{{ useCase.title }}</span>
                        </button>
                    </div>

                    <!-- Active use case -->
                    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 grid grid-cols-1 md:grid-cols-5 gap-8" role="tabpanel">
                        <div class="md:col-span-3">
                            <h3 class="text-2xl font-semibold text-gray-900">{{ currentUseCase.title }}</h3>
                            <p class="mt-3 text-gray-600 leading-relaxed">{{ currentUseCase.scenario }}</p>

                            <h4 class="mt-6 text-sm font-semibold text-gray-900">How MetMemo helps</h4>
                            <ul class="mt-3 space-y-3">
                                <li v-for="help in currentUseCase.helps" :key="help" class="flex gap-3 text-sm text-gray-600">
                                    <svg class="size-5 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ help }}
                                </li>
                            </ul>
                        </div>

                        <!-- Sample memo of someone you met -->
                        <div class="md:col-span-2 self-start">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Your memo</div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                                <div class="font-semibold text-gray-900">{{ currentUseCase.memo.name }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">Met at {{ currentUseCase.memo.where }}</div>
                                <p class="mt-4 text-sm text-gray-700 leading-relaxed">{{ currentUseCase.memo.note }}</p>
                                <div class="mt-4 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-medium">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {{ currentUseCase.memo.due }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Two sides -->
        <section id="two-sides" class="scroll-mt-20 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">Both sides of the handshake</p>
                    <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">Useful for you and the people you meet</h2>
                </div>

                <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div v-for="side in sides" :key="side.label" class="bg-white border border-gray-200 rounded-2xl p-8">
                        <h3 class="text-xl font-semibold text-gray-900">{{ side.label }}</h3>
                        <ul class="mt-6 space-y-3">
                            <li v-for="item in side.items" :key="item" class="flex gap-3 text-gray-600">
                                <svg class="size-5 shrink-0 mt-0.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="scroll-mt-20 border-t border-gray-200">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="text-center">
                    <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">FAQ</p>
                    <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">Questions, answered</h2>
                </div>

                <div class="mt-12 bg-white border border-gray-200 rounded-2xl divide-y divide-gray-200">
                    <details v-for="faq in faqs" :key="faq.question" class="group px-6">
                        <summary class="flex items-center justify-between gap-4 py-5 cursor-pointer list-none font-medium text-gray-900 [&::-webkit-details-marker]:hidden">
                            {{ faq.question }}
                            <svg class="size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </summary>
                        <p class="pb-5 -mt-1 text-gray-600 leading-relaxed">{{ faq.answer }}</p>
                    </details>
                </div>
            </div>
        </section>

        <!-- Final call to action -->
        <section class="border-t border-gray-200">
            <div class="relative overflow-hidden max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
                <div class="absolute inset-0 bg-grid pointer-events-none" aria-hidden="true" />
                <h2 class="relative text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">Your next meeting is coming.</h2>
                <p class="relative mt-4 text-gray-600">Have your card ready before it does.</p>
                <div class="relative mt-8">
                    <Link :href="canRegister ? route('register') : route('login')" class="btn-accent text-base px-6 py-3">
                        Create your free card
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-200 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-2 md:grid-cols-5 gap-8">
                <div class="col-span-2">
                    <ApplicationMark class="text-2xl" />
                    <p class="mt-4 max-w-xs text-sm text-gray-500">
                        A memo of everyone you have met. A digital business card with an AI assistant that remembers every meeting.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Product</h3>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="section in sections" :key="section.id">
                            <a :href="`#${section.id}`" class="text-gray-500 hover:text-gray-900">{{ section.label }}</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Use cases</h3>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="useCase in useCases" :key="useCase.id">
                            <a href="#use-cases" class="text-gray-500 hover:text-gray-900" @click="openUseCase(useCase.id)">{{ useCase.title }}</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Account</h3>
                    <ul class="mt-4 space-y-2 text-sm">
                        <template v-if="$page.props.auth.user">
                            <li><Link :href="route('dashboard')" class="text-gray-500 hover:text-gray-900">Dashboard</Link></li>
                            <li><Link :href="route('cards.index')" class="text-gray-500 hover:text-gray-900">My Cards</Link></li>
                            <li><Link :href="route('contacts.index')" class="text-gray-500 hover:text-gray-900">Contacts</Link></li>
                        </template>
                        <template v-else-if="canLogin">
                            <li><Link :href="route('login')" class="text-gray-500 hover:text-gray-900">Log in</Link></li>
                            <li v-if="canRegister"><Link :href="route('register')" class="text-gray-500 hover:text-gray-900">Create an account</Link></li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-gray-500">
                    <p>&copy; {{ new Date().getFullYear() }} MetMemo. All rights reserved.</p>
                    <p><span class="font-medium text-gray-700">Met</span> the people. <span class="font-medium text-indigo-600">Memo</span> the moment.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
