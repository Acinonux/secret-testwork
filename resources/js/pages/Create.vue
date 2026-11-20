<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SecretLayout from '@/layouts/SecretLayout.vue';
import { ApiError, createSecret } from '@/lib/api';
import { encrypt, fileToPayload, type SecretPayload } from '@/lib/crypto';
import { formatBytes, ttlLabel } from '@/lib/format';
import { FileText, Loader2, Type } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ ttlOptions: number[]; defaultTtl: number; maxFileBytes: number }>();

const mode = ref<'text' | 'file'>('text');
const text = ref('');
const file = ref<File | null>(null);
const ttl = ref(props.defaultTtl);
const busy = ref(false);
const error = ref('');
const result = ref<{ link: string; expiresAt: Date } | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const fileError = computed(() =>
    file.value && file.value.size > props.maxFileBytes ? `Файл больше ${formatBytes(props.maxFileBytes)}` : '',
);
const canSubmit = computed(() =>
    !busy.value && (mode.value === 'text' ? text.value.trim().length > 0 : !!file.value && !fileError.value),
);

function onFileChange(event: Event) {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

async function submit() {
    if (!canSubmit.value) return;
    busy.value = true;
    error.value = '';
    try {
        const payload: SecretPayload =
            mode.value === 'text' ? { type: 'text', text: text.value } : await fileToPayload(file.value!);
        const { blob, key } = await encrypt(payload);
        const { id, expires_at } = await createSecret(blob, ttl.value);

        result.value = { link: `${location.origin}/s/${id}#${key}`, expiresAt: new Date(expires_at) };
        reset();
    } catch (e) {
        error.value =
            e instanceof ApiError && e.status === 429
                ? 'Слишком много запросов, попробуйте через минуту.'
                : 'Не удалось создать секрет. Попробуйте ещё раз.';
    } finally {
        busy.value = false;
    }
}

function reset() {
    text.value = '';
    file.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function createAnother() {
    result.value = null;
}
</script>

<template>
    <SecretLayout title="Новый секрет">
        <Card v-if="result">
            <CardHeader>
                <CardTitle>Ссылка готова</CardTitle>
                <CardDescription>
                    Секрет можно открыть один раз. Без просмотра он удалится
                    {{ result.expiresAt.toLocaleString('ru-RU') }}.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <Input :model-value="result.link" readonly class="font-mono text-xs" @focus="($event.target as HTMLInputElement).select()" />
                <p class="text-xs text-muted-foreground">Ключ расшифровки содержится только в этой ссылке. Восстановить её нельзя.</p>
            </CardContent>
            <CardFooter class="gap-2">
                <CopyButton :value="result.link" label="Копировать ссылку" />
                <Button variant="ghost" @click="createAnother">Создать ещё</Button>
            </CardFooter>
        </Card>

        <Card v-else>
            <CardHeader>
                <CardTitle>Поделиться секретом</CardTitle>
                <CardDescription>Пароль, токен или файл. Получатель сможет открыть его только один раз.</CardDescription>
            </CardHeader>
            <form @submit.prevent="submit">
                <CardContent class="space-y-5">
                    <div class="inline-flex rounded-md border p-1" role="tablist">
                        <Button type="button" size="sm" :variant="mode === 'text' ? 'secondary' : 'ghost'" @click="mode = 'text'">
                            <Type class="size-4" /> Текст
                        </Button>
                        <Button type="button" size="sm" :variant="mode === 'file' ? 'secondary' : 'ghost'" @click="mode = 'file'">
                            <FileText class="size-4" /> Файл
                        </Button>
                    </div>

                    <div v-if="mode === 'text'" class="space-y-2">
                        <Label for="secret-text">Секрет</Label>
                        <textarea
                            id="secret-text"
                            v-model="text"
                            rows="6"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Вставьте секретный текст"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </div>

                    <div v-else class="space-y-2">
                        <Label for="secret-file">Файл (до {{ formatBytes(maxFileBytes) }})</Label>
                        <input
                            id="secret-file"
                            ref="fileInput"
                            type="file"
                            class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm"
                            @change="onFileChange"
                        />
                        <p v-if="file && !fileError" class="text-xs text-muted-foreground">{{ file.name }}, {{ formatBytes(file.size) }}</p>
                        <p v-if="fileError" class="text-sm text-destructive">{{ fileError }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="secret-ttl">Удалить через</Label>
                        <select
                            id="secret-ttl"
                            v-model.number="ttl"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm sm:w-48"
                        >
                            <option v-for="option in ttlOptions" :key="option" :value="option">{{ ttlLabel(option) }}</option>
                        </select>
                    </div>

                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                </CardContent>
                <CardFooter>
                    <Button type="submit" :disabled="!canSubmit">
                        <Loader2 v-if="busy" class="size-4 animate-spin" />
                        Зашифровать и создать ссылку
                    </Button>
                </CardFooter>
            </form>
        </Card>
    </SecretLayout>
</template>
