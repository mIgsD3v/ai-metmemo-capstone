<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrintCard from '@/Components/PrintCard.vue';

const props = defineProps({
    card: Object,
});

const design = ref('clean');
const layout = ref('sheet');
const showBrand = ref(true);
const qr = ref(null);

const designs = [
    { id: 'clean', label: 'Clean', swatch: ['#ffffff', '#16a34a'] },
    { id: 'dark', label: 'Dark', swatch: ['#0d0f0e', '#22e36b'] },
    { id: 'green', label: 'Green', swatch: ['#15803d', '#ffffff'] },
];

const layouts = [
    { id: 'sheet', label: 'Sheet of 10', hint: 'A4 paper with cut lines. Print at the office or a print shop.' },
    { id: 'single', label: 'Single card', hint: '90 × 54 mm. For pre-cut business card paper.' },
];

const shortUrl = computed(() => {
    try {
        const url = new URL(props.card.public_url);
        return url.host + url.pathname;
    } catch {
        return props.card.public_url;
    }
});

// A QR code pointing at a local address only works on the same Wi-Fi.
const isLocalUrl = computed(() => {
    try {
        const host = new URL(props.card.public_url).hostname;
        return host === 'localhost'
            || /^127\./.test(host)
            || /^10\./.test(host)
            || /^192\.168\./.test(host)
            || /^172\.(1[6-9]|2\d|3[01])\./.test(host);
    } catch {
        return false;
    }
});

onMounted(async () => {
    // High resolution so it stays sharp on paper; black on white for reliable scanning.
    qr.value = await QRCode.toDataURL(props.card.public_url, { width: 720, margin: 0, errorCorrectionLevel: 'M' });
});

// Print setup: only the cards print, at the right paper size.
let printStyle;
const applyPrintStyle = () => {
    const page = layout.value === 'sheet' ? 'size: A4; margin: 0;' : 'size: 90mm 54mm; margin: 0;';
    printStyle.textContent = `
        #card-print-root { display: none; }
        @media print {
            @page { ${page} }
            html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
            body > *:not(#card-print-root) { display: none !important; }
            #card-print-root { display: block !important; }
        }`;
};

onMounted(() => {
    printStyle = document.createElement('style');
    document.head.appendChild(printStyle);
    applyPrintStyle();
});
watch(layout, applyPrintStyle);
onBeforeUnmount(() => printStyle?.remove());

const printCards = () => window.print();
const perSheet = computed(() => (layout.value === 'sheet' ? 10 : 1));
</script>

<template>
    <AppLayout title="Print card">
        <div class="py-6 sm:py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div>
                    <Link :href="route('cards.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                        My Cards
                    </Link>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Print your card</h1>
                    <p class="text-sm text-gray-500 mt-1">A real business card with your details on the front and a QR code to your MetMemo page on the back.</p>
                </div>

                <!-- Warnings -->
                <div v-if="isLocalUrl" class="rounded-xl border border-amber-300 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 px-4 py-3 text-sm">
                    <p class="font-semibold">Test prints only for now</p>
                    <p class="mt-0.5">The QR code points to <span class="font-mono">{{ shortUrl }}</span>, which only works on your own Wi-Fi. Print real cards after MetMemo is deployed to a public address.</p>
                </div>
                <div v-if="! card.is_public" class="rounded-xl border border-amber-300 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 px-4 py-3 text-sm">
                    This card is hidden, so its QR code will not open until you make it public in
                    <Link :href="route('cards.edit', card.id)" class="font-semibold underline">card settings</Link>.
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <!-- Options -->
                    <section class="bg-white border border-gray-200 rounded-2xl divide-y divide-gray-200 order-2 lg:order-1">
                        <div class="p-5 space-y-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Design</h2>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="option in designs"
                                    :key="option.id"
                                    type="button"
                                    class="rounded-xl border p-2 text-sm font-medium transition"
                                    :class="design === option.id ? 'border-indigo-500 ring-1 ring-indigo-500 text-gray-900' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
                                    :aria-pressed="design === option.id"
                                    @click="design = option.id"
                                >
                                    <span class="block h-10 rounded-lg border border-black/10 relative overflow-hidden" :style="{ background: option.swatch[0] }">
                                        <span class="absolute left-2 top-2 h-1 w-5 rounded" :style="{ background: option.swatch[1] }" />
                                        <span class="absolute left-2 bottom-2 h-1 w-9 rounded opacity-60" :style="{ background: option.swatch[1] }" />
                                    </span>
                                    <span class="block mt-1.5">{{ option.label }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="p-5 space-y-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Paper</h2>
                            <label
                                v-for="option in layouts"
                                :key="option.id"
                                class="flex gap-3 rounded-xl border p-3 cursor-pointer transition"
                                :class="layout === option.id ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200 hover:border-gray-300'"
                            >
                                <input v-model="layout" type="radio" name="layout" :value="option.id" class="mt-0.5">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ option.label }}</span>
                                    <span class="block text-xs text-gray-500">{{ option.hint }}</span>
                                </span>
                            </label>
                        </div>

                        <div class="p-5">
                            <label class="flex items-center justify-between gap-3 cursor-pointer">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">Show "made with metmemo"</span>
                                    <span class="block text-xs text-gray-500">Small mark on the back of the card</span>
                                </span>
                                <input v-model="showBrand" type="checkbox" class="rounded">
                            </label>
                        </div>

                        <div class="p-5 space-y-2">
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Printing tips</h2>
                            <ul class="space-y-1.5 text-sm text-gray-600">
                                <li>• In the print window set <b class="text-gray-900">Margins: None</b> and <b class="text-gray-900">Scale: 100%</b>.</li>
                                <li>• Turn on <b class="text-gray-900">Background graphics</b> for the Dark and Green designs.</li>
                                <li>• For two-sided cards choose <b class="text-gray-900">Flip on long edge</b>, or print the back page on the reverse.</li>
                                <li>• Use 250–300 gsm card stock for a real card feel.</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Preview and actions -->
                    <section class="lg:col-span-2 order-1 lg:order-2 space-y-4">
                        <div class="rounded-2xl border border-gray-200 bg-gray-100 p-5 sm:p-8 grid gap-6 justify-items-center">
                            <figure class="space-y-2">
                                <div class="preview shadow-xl rounded-[3px] overflow-hidden"><div class="preview-inner"><PrintCard :card="card" side="front" :design="design" /></div></div>
                                <figcaption class="text-center text-xs text-gray-500">Front</figcaption>
                            </figure>
                            <figure class="space-y-2">
                                <div class="preview shadow-xl rounded-[3px] overflow-hidden"><div class="preview-inner"><PrintCard :card="card" side="back" :design="design" :qr="qr" :short-url="shortUrl" :show-brand="showBrand" /></div></div>
                                <figcaption class="text-center text-xs text-gray-500">Back · 90 × 54 mm</figcaption>
                            </figure>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button type="button" class="btn-accent gap-2 py-3 sm:col-span-1" :disabled="! qr" @click="printCards">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                                Print {{ perSheet === 10 ? '10 cards' : '1 card' }}
                            </button>
                            <a :href="route('cards.vcard', card.id)" class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-white transition">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Download vCard
                            </a>
                            <a :href="card.public_url" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-white transition">
                                Open public page
                            </a>
                        </div>
                        <p class="text-xs text-gray-500 text-center sm:text-start">Tip: in the print window you can also choose <b>Save as PDF</b> and send the file to a print shop.</p>
                    </section>
                </div>
            </div>
        </div>

        <!-- What actually prints: fronts on page 1, backs on page 2 -->
        <Teleport to="body">
            <div id="card-print-root">
                <template v-if="layout === 'sheet'">
                    <div v-for="side in ['front', 'back']" :key="side" class="print-sheet">
                        <PrintCard
                            v-for="n in 10"
                            :key="n"
                            :card="card"
                            :side="side"
                            :design="design"
                            :qr="qr"
                            :short-url="shortUrl"
                            :show-brand="showBrand"
                            cut-lines
                        />
                    </div>
                </template>
                <template v-else>
                    <div v-for="side in ['front', 'back']" :key="side" class="print-single">
                        <PrintCard :card="card" :side="side" :design="design" :qr="qr" :short-url="shortUrl" :show-brand="showBrand" />
                    </div>
                </template>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
/* Preview the true-size card scaled up on larger screens. */
.preview { --s: 0.85; width: calc(90mm * var(--s)); height: calc(54mm * var(--s)); }
.preview-inner { transform: scale(var(--s)); transform-origin: top left; }
@media (min-width: 640px) { .preview { --s: 1.25; } }
@media (min-width: 1024px) { .preview { --s: 1.35; } }
</style>

<style>
.print-sheet {
    width: 210mm;
    height: 297mm;
    display: grid;
    grid-template-columns: repeat(2, 90mm);
    grid-template-rows: repeat(5, 54mm);
    place-content: center;
    break-after: page;
    page-break-after: always;
}
.print-single {
    width: 90mm;
    height: 54mm;
    break-after: page;
    page-break-after: always;
}
.print-sheet:last-child,
.print-single:last-child {
    break-after: auto;
    page-break-after: auto;
}
</style>
