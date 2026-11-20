<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Check, Copy } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ value: string; label?: string }>();
const copied = ref(false);

async function copy() {
    await navigator.clipboard.writeText(props.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Button type="button" variant="outline" @click="copy">
        <Check v-if="copied" class="size-4" />
        <Copy v-else class="size-4" />
        {{ copied ? 'Скопировано' : (label ?? 'Копировать') }}
    </Button>
</template>
