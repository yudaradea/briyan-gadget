<script setup>
import { ref, watch } from "vue";

const props = defineProps({
    modelValue: { type: [Number, String], default: "" },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);

const displayValue = ref("");

function formatNumber(num) {
    if (!num && num !== 0) return "";
    return Number(num).toLocaleString("id-ID");
}

function parseRaw(str) {
    if (!str) return "";
    return str.replace(/[^0-9]/g, "");
}

// Sync display from modelValue on mount / external change
watch(
    () => props.modelValue,
    (val) => {
        const raw = parseRaw(String(val));
        displayValue.value = raw ? formatNumber(raw) : "";
    },
    { immediate: true },
);

function onInput(e) {
    // Get cursor position before formatting
    const el = e.target;
    const cursorPos = el.selectionStart;
    const oldLen = el.value.length;

    const raw = parseRaw(el.value);
    const formatted = raw ? formatNumber(raw) : "";
    displayValue.value = formatted;
    emit("update:modelValue", raw ? Number(raw) : "");

    // Adjust cursor position after dots are added/removed
    const newLen = formatted.length;
    const diff = newLen - oldLen;
    requestAnimationFrame(() => {
        const newPos = Math.max(0, cursorPos + diff);
        el.setSelectionRange(newPos, newPos);
    });
}
</script>

<template>
    <div class="relative">
        <span
            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium pointer-events-none select-none"
            >Rp</span
        >
        <input
            type="text"
            inputmode="numeric"
            :value="displayValue"
            @input="onInput"
            :placeholder="'0'"
            :required="required"
            :disabled="disabled"
            class="w-full pl-9 pr-3 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-400 bg-slate-50/50 transition-all duration-200 hover:border-slate-300"
        />
    </div>
</template>
