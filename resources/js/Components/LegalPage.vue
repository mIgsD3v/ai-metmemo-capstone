<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

// The Terms and Privacy pages: Jetstream renders resources/markdown/*.md to HTML; this lays it out.
const props = defineProps({
    title: { type: String, required: true },
    html: { type: String, required: true },
    current: { type: String, required: true }, // 'terms' | 'policy'
    summary: String,
    highlights: { type: Array, default: () => [] }, // [{ title, text }]
});

// The markdown's own "# Title" and "*Last updated*" line move into the page header; each "## Section" gets an
// id so the contents list can link to it.
const parsed = computed(() => {
    const doc = new DOMParser().parseFromString(props.html, 'text/html');
    doc.querySelector('h1')?.remove();

    const first = doc.body.querySelector('p');
    let updated = null;
    if (first?.textContent.trim().toLowerCase().startsWith('last updated')) {
        updated = first.textContent.replace(/^last updated:?\s*/i, '').trim();
        first.remove();
    }

    const sections = [...doc.querySelectorAll('h2')].map((heading, i) => {
        heading.id = `section-${i + 1}`;
        return { id: heading.id, label: heading.textContent };
    });

    return { body: doc.body.innerHTML, updated, sections };
});

const tabs = [
    { key: 'terms', label: 'Terms of Service', route: 'terms.show' },
    { key: 'policy', label: 'Privacy Policy', route: 'policy.show' },
];
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-page text-gray-900">
        <header class="sticky top-0 z-30 border-b border-gray-200 bg-page/85 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" aria-label="MetMemo home"><ApplicationMark class="!text-xl" /></Link>
                <div class="flex items-center gap-2">
                    <ThemeToggle />
                    <Link v-if="$page.props.auth?.user" :href="route('dashboard')" class="btn-quiet h-10 px-3.5 text-sm">Dashboard</Link>
                    <Link v-else :href="route('login')" class="btn-quiet h-10 px-3.5 text-sm">Log in</Link>
                </div>
            </div>
        </header>

        <!-- Title -->
        <section class="border-b border-gray-200 bg-indigo-50/60 bg-grid-lines">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
                <p class="eyebrow !text-indigo-600">Legal</p>
                <h1 class="mt-2 text-3xl sm:text-[40px] font-bold leading-tight tracking-[-0.03em]">{{ title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                    <span v-if="parsed.updated" class="inline-flex h-7 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-3 text-xs font-medium text-gray-600">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        Last updated {{ parsed.updated }}
                    </span>
                </div>
                <p v-if="summary" class="mt-4 max-w-2xl text-[15px] leading-relaxed text-gray-600">{{ summary }}</p>

                <ul v-if="highlights.length" class="mt-7 grid gap-3 sm:grid-cols-3">
                    <li v-for="item in highlights" :key="item.title" class="panel flex gap-3 p-4">
                        <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-accent/15 text-indigo-600">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold">{{ item.title }}</span>
                            <span class="mt-0.5 block text-[13px] leading-snug text-gray-500">{{ item.text }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-10">
            <!-- Switch between the two documents, and this one's contents -->
            <aside class="mb-6 lg:mb-0">
                <div class="lg:sticky lg:top-24 space-y-6">
                    <nav aria-label="Legal documents" class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 lg:flex-col">
                        <Link
                            v-for="tab in tabs"
                            :key="tab.key"
                            :href="route(tab.route)"
                            class="flex-1 rounded-lg px-3 py-2 text-center text-sm font-medium transition lg:text-start"
                            :class="tab.key === current ? 'bg-accent/10 text-indigo-600' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
                            :aria-current="tab.key === current ? 'page' : undefined"
                        >{{ tab.label }}</Link>
                    </nav>

                    <nav v-if="parsed.sections.length" aria-label="On this page" class="hidden lg:block">
                        <p class="eyebrow mb-2 px-3">On this page</p>
                        <a
                            v-for="section in parsed.sections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="block rounded-lg px-3 py-1.5 text-[13px] text-gray-500 transition hover:bg-gray-50 hover:text-gray-900"
                        >{{ section.label }}</a>
                    </nav>
                </div>
            </aside>

            <!-- The document -->
            <article
                class="panel px-5 py-7 sm:px-10 sm:py-10 prose max-w-none
                    prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-gray-900
                    prose-h2:mt-10 prose-h2:mb-3 prose-h2:scroll-mt-24 prose-h2:border-t prose-h2:border-gray-200 prose-h2:pt-8 prose-h2:text-xl
                    prose-p:text-[15px] prose-p:leading-7 prose-p:text-gray-600
                    prose-li:text-[15px] prose-li:leading-7 prose-li:text-gray-600 prose-li:my-1.5 prose-li:marker:text-indigo-600
                    prose-strong:text-gray-900 prose-strong:font-semibold
                    prose-a:font-medium prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline"
                v-html="parsed.body"
            />
        </div>

        <footer class="border-t border-gray-200">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-6 text-[13px] text-gray-400 sm:flex-row sm:px-6">
                <span>© {{ new Date().getFullYear() }} MetMemo. A capstone project.</span>
                <span class="flex gap-4">
                    <Link :href="route('terms.show')" class="hover:text-gray-700">Terms</Link>
                    <Link :href="route('policy.show')" class="hover:text-gray-700">Privacy</Link>
                    <a href="mailto:albert.miguela@herma.com.ph" class="hover:text-gray-700">Contact</a>
                </span>
            </div>
        </footer>
    </div>
</template>
