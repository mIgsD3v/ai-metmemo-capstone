<script setup>
import { computed, ref, watch } from 'vue';
import { getCountries, getCountryCallingCode, getExampleNumber, parsePhoneNumberFromString } from 'libphonenumber-js';
import examples from 'libphonenumber-js/examples.mobile.json';
import { phoneIcons } from '@/phone';

// A country code picker (Philippines first) and the number. The value is the whole international number,
// e.g. "+63 917 123 4567", which the server checks against that country's numbering plan.
const props = defineProps({
    modelValue: { type: String, default: '' },
    id: String,
    kind: { type: String, default: 'mobile' }, // 'mobile' or 'telephone': the icon and placeholder
    fieldClass: { type: String, default: 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm' },
    icon: { type: Boolean, default: false },
    required: Boolean,
    autocomplete: { type: String, default: 'off' },
});

const emit = defineEmits(['update:modelValue']);

const DEFAULT_COUNTRY = 'PH';

const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });
const countries = getCountries()
    .map((code) => ({ code, name: regionNames.of(code) ?? code, dial: getCountryCallingCode(code) }))
    .sort((a, b) => (a.code === DEFAULT_COUNTRY ? -1 : b.code === DEFAULT_COUNTRY ? 1 : a.name.localeCompare(b.name)));

// "+63 917 123 4567" -> "917 123 4567": the country code is already in the picker.
const withoutCountryCode = (number) => number.formatInternational().replace(/^\+\d+\s*/, '');

const country = ref(DEFAULT_COUNTRY);
const national = ref('');
const touched = ref(false);
let emitted = null;

// Fill the picker and the number from a saved or scanned value ("0917 123 4567" reads as Philippine).
const load = (value) => {
    const number = value ? parsePhoneNumberFromString(value, DEFAULT_COUNTRY) : undefined;
    country.value = number?.country ?? DEFAULT_COUNTRY;
    national.value = number ? withoutCountryCode(number) : (value ?? '');
};
load(props.modelValue);

watch(() => props.modelValue, (value) => {
    if (value !== emitted) load(value);
});

const parsed = computed(() => {
    const text = national.value.trim();
    if (! text) return undefined;

    return text.startsWith('+') ? parsePhoneNumberFromString(text) : parsePhoneNumberFromString(text, country.value);
});

const update = () => {
    const text = national.value.trim();
    const number = parsed.value;

    // Typed or pasted with a country code ("+1 415…"): follow it in the picker.
    if (text.startsWith('+') && number?.country) {
        country.value = number.country;
    }

    emitted = ! text ? '' : number?.isValid() ? number.formatInternational() : `+${getCountryCallingCode(country.value)} ${text.replace(/^\+\d+\s*/, '')}`;
    emit('update:modelValue', emitted);
};

const onBlur = () => {
    touched.value = true;
    if (parsed.value?.isValid()) national.value = withoutCountryCode(parsed.value);
    update();
};

const selected = computed(() => countries.find((c) => c.code === country.value) ?? countries[0]);
const invalid = computed(() => touched.value && national.value.trim() !== '' && ! parsed.value?.isValid());

const placeholder = computed(() => {
    if (props.kind === 'telephone') return 'Landline number';
    const example = getExampleNumber(country.value, examples);

    return example ? withoutCountryCode(example) : 'Mobile number';
});
</script>

<template>
    <div>
        <div class="flex gap-2">
            <!-- The native picker (searchable on phones); the box itself shows only the short code. -->
            <div class="relative w-[6.75rem] shrink-0">
                <select
                    v-model="country"
                    :aria-label="`Country code for ${kind === 'telephone' ? 'telephone' : 'mobile'}`"
                    class="block w-full !text-transparent"
                    :class="fieldClass"
                    @change="update"
                >
                    <option v-for="c in countries" :key="c.code" :value="c.code" class="text-gray-900">{{ c.name }} (+{{ c.dial }})</option>
                </select>
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center gap-1.5 text-sm text-gray-900">
                    <span class="text-xs font-semibold text-gray-500">{{ selected.code }}</span>
                    +{{ selected.dial }}
                </span>
            </div>
            <div class="relative min-w-0 flex-1">
                <svg v-if="icon" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="phoneIcons[kind]" />
                </svg>
                <input
                    :id="id"
                    v-model="national"
                    type="tel"
                    inputmode="tel"
                    :autocomplete="autocomplete"
                    :placeholder="placeholder"
                    :required="required"
                    class="block w-full"
                    :class="[fieldClass, icon ? '!ps-9' : '']"
                    @input="update"
                    @blur="onBlur"
                >
            </div>
        </div>
        <p v-if="invalid" class="mt-1 text-xs text-amber-600 dark:text-amber-300">
            This doesn't look like a {{ selected.name }} number. Check the country code and the number.
        </p>
    </div>
</template>
