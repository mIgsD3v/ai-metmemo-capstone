<script setup>
import { computed } from 'vue';

// A business card at real print size (90 x 54 mm). Colors are fixed per design, not tied to the app theme,
// so what you preview is exactly what prints.
const props = defineProps({
    card: { type: Object, required: true },
    side: { type: String, default: 'front' },
    design: { type: String, default: 'clean' },
    qr: String,
    shortUrl: String,
    showBrand: { type: Boolean, default: true },
    cutLines: { type: Boolean, default: false },
});

const designs = {
    clean: { bg: '#ffffff', ink: '#14161a', muted: '#5b616b', accent: '#16a34a', rule: '#e5e7eb', backBg: '#ffffff', backInk: '#14161a', backMuted: '#5b616b' },
    dark: { bg: '#0d0f0e', ink: '#f5f7f5', muted: '#a3aba4', accent: '#22e36b', rule: '#2a2e2b', backBg: '#0d0f0e', backInk: '#f5f7f5', backMuted: '#a3aba4' },
    green: { bg: '#15803d', ink: '#ffffff', muted: '#d1fae5', accent: '#ffffff', rule: '#22a052', backBg: '#ffffff', backInk: '#14161a', backMuted: '#5b616b' },
};

const d = computed(() => designs[props.design] ?? designs.clean);

const contacts = computed(() => [
    // Email and website take a full line each; phone and location share the last line.
    props.card.email && { icon: 'email', text: props.card.email },
    props.card.website && { icon: 'web', text: props.card.website.replace(/^https?:\/\//, '').replace(/[?#].*$/, '').replace(/\/$/, '') },
    props.card.phone && { icon: 'phone', text: props.card.phone },
    props.card.location && { icon: 'pin', text: props.card.location },
].filter(Boolean));

const icons = {
    email: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    phone: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
    web: 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
    pin: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
};
</script>

<template>
    <div
        class="print-card"
        :class="{ 'with-cut-lines': cutLines }"
        :style="{ background: side === 'front' ? d.bg : d.backBg, color: side === 'front' ? d.ink : d.backInk }"
    >
        <!-- Front -->
        <div v-if="side === 'front'" class="front">
            <div class="top">
                <span class="bar" :style="{ background: d.accent }" />
                <span v-if="card.company" class="company" :style="{ color: d.accent }">{{ card.company }}</span>
            </div>

            <div class="identity">
                <div class="name">{{ card.full_name }}</div>
                <div v-if="card.job_title" class="title" :style="{ color: d.muted }">{{ card.job_title }}</div>
            </div>

            <ul class="contacts" :style="{ borderColor: d.rule }">
                <li v-for="item in contacts" :key="item.icon" :class="{ wide: item.icon === 'email' || item.icon === 'web' }">
                    <svg viewBox="0 0 24 24" fill="none" :stroke="d.accent" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                    </svg>
                    <span>{{ item.text }}</span>
                </li>
            </ul>
        </div>

        <!-- Back -->
        <div v-else class="back">
            <div class="qr-tile">
                <img v-if="qr" :src="qr" alt="QR code to this card">
            </div>
            <div class="back-text">
                <div class="scan">Scan to save<br>my contact</div>
                <div class="url" :style="{ color: d.backMuted }">{{ shortUrl }}</div>
                <div v-if="showBrand" class="brand" :style="{ color: d.backMuted }">
                    made with
                    <svg viewBox="0 0 32 32" fill="none" aria-hidden="true">
                        <circle cx="7" cy="6.5" r="3.2" :fill="d.backInk" />
                        <path d="M7 26V13.5l9 9" :stroke="d.backInk" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="25" cy="6.5" r="3.2" fill="#16a34a" />
                        <path d="M16 22.5l9-9V26" stroke="#16a34a" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <b :style="{ color: d.backInk }">met<span style="color:#16a34a">memo</span></b>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.print-card {
    width: 90mm;
    height: 54mm;
    box-sizing: border-box;
    overflow: hidden;
    position: relative;
    font-family: Inter, 'Segoe UI', Arial, sans-serif;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.with-cut-lines { outline: 0.2mm dashed #b8bcc2; outline-offset: -0.1mm; }

.front { height: 100%; padding: 6mm 6.5mm 5.5mm; display: flex; flex-direction: column; }
.top { display: flex; align-items: center; gap: 2.2mm; }
.bar { width: 7mm; height: 1mm; border-radius: 1mm; }
.company { font-size: 6.4pt; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.identity { margin-top: 4.5mm; }
.name { font-size: 13.5pt; font-weight: 700; letter-spacing: -0.01em; line-height: 1.1; }
.title { margin-top: 1.2mm; font-size: 7.6pt; font-weight: 500; }
.contacts { list-style: none; margin: auto 0 0; padding: 2.6mm 0 0; border-top: 0.25mm solid; display: grid; grid-template-columns: 1fr 1fr; gap: 1.2mm 3mm; }
.contacts li { display: flex; align-items: center; gap: 1.4mm; font-size: 6.6pt; min-width: 0; }
.contacts li span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Emails and websites are long: give them the full width so they are not cut off. */
.contacts li.wide { grid-column: 1 / -1; }
.contacts svg { width: 2.8mm; height: 2.8mm; flex: none; }

.back { height: 100%; padding: 6mm; display: flex; align-items: center; gap: 5.5mm; }
.qr-tile { width: 34mm; height: 34mm; flex: none; background: #fff; border-radius: 2.2mm; padding: 1.6mm; box-sizing: border-box; }
.qr-tile img { width: 100%; height: 100%; display: block; image-rendering: pixelated; }
.back-text { display: flex; flex-direction: column; gap: 2.4mm; min-width: 0; }
.scan { font-size: 10.5pt; font-weight: 700; line-height: 1.15; }
.url { font-size: 6.2pt; word-break: break-all; }
.brand { display: flex; align-items: center; gap: 1mm; font-size: 5.6pt; margin-top: 1.5mm; }
.brand svg { width: 3mm; height: 3mm; }
.brand b { font-size: 6.4pt; }
</style>
