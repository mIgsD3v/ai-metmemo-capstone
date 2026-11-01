<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const showingProductMenu = ref(false);

// Product tour: plays muted while on screen, pauses when scrolled away. Never autoplays for people
// who prefer reduced motion. The "Watch the tour" button plays it with sound (a click allows that).
const tourVideo = ref(null);
let tourObserver;

const playTour = () => {
    const video = tourVideo.value;
    if (! video) return;
    video.muted = false;
    video.currentTime = 0;
    video.play().catch(() => {});
};

onMounted(() => {
    const video = tourVideo.value;
    if (! video || ! ('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    tourObserver = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
            if (video.paused && video.muted && ! video.ended) video.play().catch(() => {});
        } else if (! video.paused) {
            video.pause();
        }
    }, { threshold: [0, 0.5] });
    tourObserver.observe(video);
});

onUnmounted(() => tourObserver?.disconnect());

// How it works: a roadmap that follows the scroll. Scrolling sets where the line should be; the line
// then draws there at its own pace (ROAD_SPEED), so a fast scroll still shows 01, 02, 03, 04 light up
// in turn instead of jumping to the end. Scrolling up takes it back the same way.
const ROAD_SPEED = 1.7; // milestones per second
const stepsSection = ref(null);
const roadTarget = ref(0); // where the scroll says the line should be: 0 = first milestone, steps.length - 1 = last
const roadProgress = ref(0); // where the line is drawn now, catching up with roadTarget
let roadFrame = null;
let roadTick = null;
let roadLastTime = 0;

const drawRoad = (time) => {
    const elapsed = roadLastTime ? (time - roadLastTime) / 1000 : 0;
    roadLastTime = time;

    const gap = roadTarget.value - roadProgress.value;
    const step = Math.min(Math.abs(gap), ROAD_SPEED * elapsed);
    roadProgress.value += Math.sign(gap) * step;

    if (Math.abs(roadTarget.value - roadProgress.value) > 0.001) {
        roadTick = requestAnimationFrame(drawRoad);
    } else {
        roadProgress.value = roadTarget.value;
        roadTick = null;
        roadLastTime = 0;
    }
};

const setRoadTarget = (value) => {
    roadTarget.value = value;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        roadProgress.value = value;
        return;
    }
    roadTick ??= requestAnimationFrame(drawRoad);
};

const clamp = (value) => Math.min(1, Math.max(0, value));

const measureRoad = () => {
    roadFrame = null;
    const list = stepsSection.value;
    if (! list) return;

    const top = list.getBoundingClientRect().top;
    const screen = window.innerHeight;

    if (window.matchMedia('(min-width: 1024px)').matches) {
        // Side by side: drawn over about 40% of a screen of scrolling, from when it is 75% down the screen.
        setRoadTarget(clamp((screen * 0.75 - top) / (screen * 0.4)) * (steps.length - 1));
        return;
    }

    // Stacked (phones): the line follows a point 60% down the screen, milestone by milestone.
    const reading = screen * 0.6 - top;
    const marks = [...list.children].map((item) => item.offsetTop);
    let progress = 0;
    marks.forEach((mark, i) => {
        if (i > 0 && reading > marks[i - 1]) progress = i - 1 + clamp((reading - marks[i - 1]) / (mark - marks[i - 1]));
    });
    setRoadTarget(progress);
};
const onRoadScroll = () => {
    roadFrame ??= requestAnimationFrame(measureRoad);
};

const currentStep = computed(() => Math.floor(roadProgress.value + 0.001));
const nodeState = (i) => {
    if (i < currentStep.value) return 'done';
    return i === currentStep.value ? 'active' : 'todo';
};
const connectorFill = (i) => clamp(roadProgress.value - i);

onMounted(() => {
    measureRoad();
    window.addEventListener('scroll', onRoadScroll, { passive: true });
    window.addEventListener('resize', onRoadScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onRoadScroll);
    window.removeEventListener('resize', onRoadScroll);
    if (roadFrame) cancelAnimationFrame(roadFrame);
    if (roadTick) cancelAnimationFrame(roadTick);
});
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
        when: 'Before you go',
        title: 'Create your card',
        body: 'Add your name, role, company, contact details and a short bio. Make as many cards as you have hats to wear.',
    },
    {
        when: 'When you meet',
        title: 'Share it in a second',
        body: 'Every card gets its own link and QR code. The person you meet scans it with their phone camera. No app to install.',
    },
    {
        when: 'Right after',
        title: 'Capture the contact',
        body: 'Visitors save you in one tap and leave their details. Swap cards with MetMemo users, or scan their paper card.',
    },
    {
        when: 'Two days later',
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
    { group: 'Your card', title: 'QR code and share link', body: 'Every card gets its own page and short link. Save the QR as an image for your booth, slides or email signature.' },
    { group: 'Your card', title: 'Card swap', body: 'Meeting another MetMemo user? One scan and one tap, and you are in each other\'s contacts, photo included.', new: true },
    { group: 'Your card', title: 'Lead form and vCard', body: 'Visitors save you to their phone in one tap and leave their details, which arrive as a contact.' },
    { group: 'Your contacts', title: 'Contacts with notes', body: 'Everyone you meet, where you met, the card they came from, and what you talked about.' },
    { group: 'Your contacts', title: 'Reminders and alerts', body: 'New contacts are due two days after you meet. The bell tells you when someone leaves details or swaps cards.' },
    { group: 'Your contacts', title: 'Export any time', body: 'Download your contacts as CSV, Excel or vCard. Your list stays yours.' },
    { group: 'Your account', title: 'Insights', body: 'See which events and cards bring people in, and how quickly you follow up.', new: true },
    { group: 'Your account', title: 'Sign in your way', body: 'Email and password or Continue with Google, with optional two-factor authentication.', new: true },
    { group: 'Your account', title: 'Made for your phone', body: 'Scan, chat with the assistant and share your QR from a phone, with light and dark themes.' },
];

const scanPreview = [
    { label: 'Name', value: 'Juan Dela Cruz' },
    { label: 'Company', value: 'Cruz Logistics' },
    { label: 'Email', value: 'juan@cruzlog.ph', check: true },
];

const followUpPreview = [
    { name: 'Juan Dela Cruz', due: 'Due today · Expo SMX' },
    { name: 'Carla Mendoza', due: 'Due Thursday · Client visit' },
];

const sides = [
    {
        tag: 'Signed in',
        label: 'You, the card owner',
        items: [
            'Build one or more cards for each role you have',
            'Show your QR code or send your link',
            'Get every visitor lead as a contact with a follow-up date',
            'Scan paper cards you receive into contacts',
            'Swap cards with other MetMemo users in one tap',
            'Ask the assistant who to follow up with and get drafts',
        ],
    },
    {
        tag: 'No account needed',
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
        question: 'What is a card swap?',
        answer: "When you both use MetMemo, one of you scans the other's QR code and taps Swap. You each land in the other's contacts, with name, details and photo, and both of you are reminded to follow up.",
    },
    {
        question: 'Can I take my contacts with me?',
        answer: 'Yes. Export them any time as CSV, Excel or vCard, ready for Google Contacts, Outlook or your phone.',
    },
    {
        question: 'Can anyone at MetMemo read my contacts?',
        answer: "No. Administrators only see totals, such as how many accounts and contacts there are. No role can open another person's contacts, notes or assistant chats.",
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
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-accent whitespace-nowrap px-4 sm:px-5">Dashboard</Link>
                        <template v-else>
                            <Link :href="route('login')" class="hidden sm:inline-block px-3 py-2 text-gray-600 hover:text-gray-900">Log in</Link>
                            <Link v-if="canRegister" :href="route('register')" class="btn-accent whitespace-nowrap px-4 sm:px-5">Get started</Link>
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
                    <a href="#tour" class="inline-flex items-center gap-2 px-6 py-3 rounded-md border border-gray-300 text-base font-medium text-gray-700 hover:bg-white transition" @click="playTour">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 5.5v13l11-6.5z" />
                        </svg>
                        Watch the 45-second tour
                    </a>
                </div>
                <p class="mt-6 text-sm text-gray-500">
                    No app to install · Works on any phone · You stay in control of every message
                </p>
            </div>
        </section>

        <!-- Product tour video -->
        <section id="tour" class="scroll-mt-20 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 sm:pt-20">
            <div class="text-center max-w-2xl mx-auto">
                <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">Product tour</p>
                <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">See MetMemo in 45 seconds</h2>
            </div>
            <!-- Plays muted when scrolled into view (the captions are in the video); controls to unmute or replay. -->
            <div class="mt-10 overflow-hidden rounded-2xl sm:rounded-3xl border border-gray-200 bg-gray-950 shadow-xl">
                <video
                    ref="tourVideo"
                    class="block w-full aspect-video"
                    src="/videos/metmemo-intro.mp4"
                    poster="/videos/metmemo-intro-poster.jpg"
                    controls
                    muted
                    playsinline
                    preload="metadata"
                    aria-label="MetMemo product tour, 45 seconds, with captions"
                />
            </div>
            <p class="mt-3 text-center text-sm text-gray-500">Tap the speaker icon for the voice-over. Sample contacts shown.</p>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="scroll-mt-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">How it works</p>
                <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">From first hello to follow-up</h2>
                <p class="mt-4 text-gray-600">You meet someone, they scan your card, and MetMemo remembers the conversation so it does not end there.</p>
            </div>

            <!-- Roadmap: milestones on one line (down the side on phones). Scrolling draws the line from
                 one milestone to the next; the latest one reached is solid with a pulse. -->
            <ol ref="stepsSection" class="relative mt-14 grid grid-cols-1 lg:grid-cols-4">
                <li
                    v-for="(step, i) in steps"
                    :key="step.title"
                    class="relative flex gap-5 pb-10 last:pb-0 lg:flex-col lg:gap-0 lg:pb-0 lg:pe-10"
                    :aria-current="nodeState(i) === 'active' ? 'step' : undefined"
                >
                    <!-- Connector to the next milestone: below on phones, to the right on large screens -->
                    <div
                        v-if="i < steps.length - 1"
                        class="absolute start-5 top-12 bottom-2 w-0.5 -translate-x-1/2 overflow-hidden rounded-full bg-gray-200 lg:start-[3.25rem] lg:end-3 lg:top-[19px] lg:bottom-auto lg:h-px lg:w-auto lg:translate-x-0"
                        aria-hidden="true"
                    >
                        <div class="road-fill absolute inset-0 bg-accent" :style="{ '--fill': connectorFill(i) }" />
                    </div>

                    <!-- Milestone -->
                    <span class="relative z-10 flex size-10 shrink-0 items-center justify-center">
                        <span v-if="nodeState(i) === 'active'" class="absolute inset-0 rounded-full bg-accent/25 motion-safe:animate-ping" aria-hidden="true" />
                        <span
                            :key="nodeState(i)"
                            class="relative flex size-10 items-center justify-center rounded-full border text-[13px] font-semibold tabular-nums ring-[6px] ring-page transition-colors duration-300"
                            :class="{
                                'border-accent/60 bg-indigo-50 text-indigo-600': nodeState(i) === 'done',
                                'road-pop border-accent bg-accent text-accent-fg': nodeState(i) === 'active',
                                'border-gray-300 bg-page text-gray-500': nodeState(i) === 'todo',
                            }"
                        >
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                    </span>

                    <div class="min-w-0 pt-1.5 lg:mt-7 lg:pt-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] transition-colors duration-300" :class="nodeState(i) === 'todo' ? 'text-gray-400' : 'text-indigo-600'">{{ step.when }}</p>
                        <h3 class="mt-1.5 text-lg font-semibold transition-colors duration-300" :class="nodeState(i) === 'todo' ? 'text-gray-500' : 'text-gray-900'">{{ step.title }}</h3>
                        <p class="mt-2 text-[15px] leading-relaxed transition-colors duration-300" :class="nodeState(i) === 'todo' ? 'text-gray-400' : 'text-gray-600'">{{ step.body }}</p>
                    </div>
                </li>
            </ol>

            <p class="mt-14 border-s-2 border-gray-300 ps-5 text-gray-600">
                The assistant drafts, you decide. Nothing is ever sent without you.
            </p>
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
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-indigo-600">0{{ i + 1 }}</p>
                        <h3 class="mt-2 text-2xl font-semibold text-gray-900">{{ feature.title }}</h3>
                        <p class="mt-4 text-gray-600 leading-relaxed">{{ feature.body }}</p>

                        <!-- A small look at the real screen, same frame on all three cards -->
                        <div class="mt-auto pt-8">
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm" aria-hidden="true">
                                <!-- Scan: the fields it read, one flagged -->
                                <template v-if="i === 0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400">Read from the card</p>
                                    <dl class="mt-3 space-y-2">
                                        <div v-for="field in scanPreview" :key="field.label" class="flex items-center justify-between gap-3">
                                            <dt class="text-gray-500">{{ field.label }}</dt>
                                            <dd class="flex min-w-0 items-center gap-1.5 font-medium text-gray-900">
                                                <span class="truncate">{{ field.value }}</span>
                                                <span v-if="field.check" class="shrink-0 rounded-full bg-amber-400/15 px-2 py-px text-[11px] font-semibold text-amber-700 dark:text-amber-300">Check</span>
                                            </dd>
                                        </div>
                                    </dl>
                                </template>

                                <!-- Remember: a question, and the answer with its source -->
                                <template v-else-if="i === 1">
                                    <p class="ms-auto w-fit max-w-[85%] rounded-2xl rounded-ee-md bg-gray-800 px-3 py-2 text-page">What did Juan want?</p>
                                    <p class="mt-3 text-gray-700 leading-relaxed">A quote for 3 branches, decision by end of month.</p>
                                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-500">
                                        <span class="size-1.5 rounded-full bg-accent" />
                                        Your note · Expo SMX
                                    </p>
                                </template>

                                <!-- Follow up: who is due, with a draft ready -->
                                <template v-else>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400">This week</p>
                                    <ul class="mt-3 space-y-2.5">
                                        <li v-for="person in followUpPreview" :key="person.name" class="flex items-center justify-between gap-3">
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-gray-900">{{ person.name }}</span>
                                                <span class="block truncate text-xs text-gray-500">{{ person.due }}</span>
                                            </span>
                                            <span class="shrink-0 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700">Draft ready</span>
                                        </li>
                                    </ul>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Everything else, grouped: one bordered sheet, hairlines between cells -->
                <div class="mt-16">
                    <h3 class="text-xl font-semibold text-gray-900">And everything around it</h3>
                    <div class="mt-6 grid grid-cols-1 gap-px overflow-hidden rounded-2xl border border-gray-200 bg-gray-200 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="feature in coreFeatures" :key="feature.title" class="bg-white p-6">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400">{{ feature.group }}</p>
                            <h4 class="mt-2 font-semibold text-gray-900">
                                {{ feature.title }}
                                <span v-if="feature.new" class="ms-1.5 rounded-full bg-indigo-50 px-2 py-0.5 align-middle text-[11px] font-semibold text-indigo-600">New</span>
                            </h4>
                            <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">{{ feature.body }}</p>
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
                    <!-- Use case tabs: a stacked list (only three, so no sideways scrolling on phones) -->
                    <div class="flex flex-col gap-2" role="tablist">
                        <!-- Title and one line of who it's for; the active one gets the accent edge. -->
                        <button
                            v-for="useCase in useCases"
                            :key="useCase.id"
                            type="button"
                            role="tab"
                            :aria-selected="activeUseCase === useCase.id"
                            class="rounded-xl border border-s-[3px] px-4 py-3 lg:py-3.5 text-start transition"
                            :class="activeUseCase === useCase.id
                                ? 'bg-white border-gray-200 border-s-accent'
                                : 'border-gray-200 border-s-transparent hover:bg-white'"
                            @click="activeUseCase = useCase.id"
                        >
                            <span class="block text-sm font-semibold" :class="activeUseCase === useCase.id ? 'text-gray-900' : 'text-gray-700'">{{ useCase.title }}</span>
                            <span class="mt-0.5 block text-[13px] text-gray-500">{{ useCase.hint }}</span>
                        </button>
                    </div>

                    <!-- Active use case -->
                    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 grid grid-cols-1 md:grid-cols-5 gap-8" role="tabpanel">
                        <div class="md:col-span-3">
                            <h3 class="text-2xl font-semibold text-gray-900">{{ currentUseCase.title }}</h3>
                            <p class="mt-3 text-gray-600 leading-relaxed">{{ currentUseCase.scenario }}</p>

                            <h4 class="mt-6 text-sm font-semibold text-gray-900">How MetMemo helps</h4>
                            <ol class="mt-3 divide-y divide-gray-200 border-y border-gray-200">
                                <li v-for="(help, n) in currentUseCase.helps" :key="help" class="flex gap-4 py-3 text-sm text-gray-700 leading-relaxed">
                                    <span class="w-5 shrink-0 pt-px text-xs font-semibold text-indigo-600 tabular-nums">0{{ n + 1 }}</span>
                                    {{ help }}
                                </li>
                            </ol>
                        </div>

                        <!-- Sample memo of someone you met -->
                        <div class="md:col-span-2 self-start">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Your memo</div>
                            <!-- Looks like the contact in the app: avatar, where you met, the note, when to follow up -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                                <div class="flex items-center gap-3 p-4">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-600">
                                        {{ currentUseCase.memo.name.split(' ').map((part) => part[0]).slice(0, 2).join('') }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-gray-900">{{ currentUseCase.memo.name }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ currentUseCase.memo.where }}</span>
                                    </span>
                                </div>
                                <p class="border-t border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 leading-relaxed">{{ currentUseCase.memo.note }}</p>
                                <div class="flex items-center justify-between gap-3 border-t border-gray-200 px-4 py-2.5 text-xs">
                                    <span class="text-gray-500">Follow up</span>
                                    <span class="font-semibold text-gray-900">{{ currentUseCase.memo.due.replace('Follow up ', '') }}</span>
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
                        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400">{{ side.tag }}</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-900">{{ side.label }}</h3>
                        <ul class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
                            <li v-for="item in side.items" :key="item" class="py-3 text-gray-700">{{ item }}</li>
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

<style scoped>
/* How it works roadmap: each line's fill follows the scroll (--fill, 0 to 1). Down on phones, across from 1024px. */
.road-fill {
    transform: scaleY(var(--fill, 0));
    transform-origin: top;
}

/* A milestone pops as the line reaches it. */
.road-pop {
    animation: road-pop 450ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes road-pop {
    0% { transform: scale(0.8); }
    60% { transform: scale(1.15); }
    100% { transform: scale(1); }
}

@media (prefers-reduced-motion: reduce) {
    .road-pop {
        animation: none;
    }
}

@media (min-width: 1024px) {
    .road-fill {
        transform: scaleX(var(--fill, 0));
        transform-origin: left;
    }
}
</style>
