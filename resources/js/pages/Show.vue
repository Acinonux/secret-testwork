<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import SecretLayout from '@/layouts/SecretLayout.vue';
import { ApiError, revealSecret } from '@/lib/api';
import { base64ToBytes, decrypt, DecryptError, type SecretPayload } from '@/lib/crypto';
import { formatBytes } from '@/lib/format';
import { Link } from '@inertiajs/vue3';
import { Download, Eye, Loader2 } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ id: string }>();

type State = 'ready' | 'loading' | 'revealed' | 'nokey' | 'notfound' | 'badkey' | 'error';

const state = ref<State>('ready');
const secret = ref<SecretPayload | null>(null);
const download = ref<{ url: string; name: string; size: number } | null>(null);
let key = '';

onMounted(() => {
    key = location.hash.slice(1);
    if (!key) state.value = 'nokey';
});

onBeforeUnmount(() => {
    if (download.value) URL.revokeObjectURL(download.value.url);
});

async function reveal() {
    state.value = 'loading';
    try {
        const { payload } = await revealSecret(props.id);
        // Ключ больше не нужен: убираем его из адресной строки и истории.
        history.replaceState(history.state, '', location.pathname);
        const data = await decrypt(payload, key);
        key = '';

        if (data.type === 'file') {
            const bytes = base64ToBytes(data.data);
            const blob = new Blob([bytes], { type: data.mime });
            download.value = { url: URL.createObjectURL(blob), name: data.name, size: bytes.length };
        }
        secret.value = data;
        state.value = 'revealed';
    } catch (e) {
        if (e instanceof ApiError && e.status === 404) state.value = 'notfound';
        else if (e instanceof DecryptError) state.value = 'badkey';
        else state.value = 'error';
    }
}
</script>

<template>
    <SecretLayout title="Секрет">
        <Card v-if="state === 'ready' || state === 'loading'">
            <CardHeader>
                <CardTitle>Вам передали секрет</CardTitle>
                <CardDescription>Его можно просмотреть только один раз. После просмотра он будет удалён с сервера.</CardDescription>
            </CardHeader>
            <CardFooter>
                <Button :disabled="state === 'loading'" @click="reveal">
                    <Loader2 v-if="state === 'loading'" class="size-4 animate-spin" />
                    <Eye v-else class="size-4" />
                    Показать секрет
                </Button>
            </CardFooter>
        </Card>

        <Card v-else-if="state === 'revealed' && secret">
            <CardHeader>
                <CardTitle>Секрет</CardTitle>
                <CardDescription>Он уже удалён с сервера. Сохраните его, если нужно: после закрытия страницы открыть его снова нельзя.</CardDescription>
            </CardHeader>
            <CardContent>
                <pre
                    v-if="secret.type === 'text'"
                    class="max-h-96 overflow-auto whitespace-pre-wrap break-all rounded-md border bg-muted p-3 font-mono text-sm"
                    >{{ secret.text }}</pre
                >
                <p v-else-if="download" class="text-sm">
                    <span class="font-medium">{{ download.name }}</span>
                    <span class="text-muted-foreground">, {{ formatBytes(download.size) }}</span>
                </p>
            </CardContent>
            <CardFooter class="gap-2">
                <CopyButton v-if="secret.type === 'text'" :value="secret.text" />
                <Button v-else-if="download" as="a" :href="download.url" :download="download.name">
                    <Download class="size-4" /> Скачать файл
                </Button>
            </CardFooter>
        </Card>

        <Card v-else>
            <CardHeader>
                <CardTitle>
                    {{
                        {
                            nokey: 'Ссылка неполная',
                            notfound: 'Секрет не найден',
                            badkey: 'Не удалось расшифровать',
                            error: 'Что-то пошло не так',
                        }[state as 'nokey' | 'notfound' | 'badkey' | 'error']
                    }}
                </CardTitle>
                <CardDescription>
                    <template v-if="state === 'nokey'">В ссылке нет ключа расшифровки (части после «#»). Попросите отправителя прислать ссылку целиком.</template>
                    <template v-else-if="state === 'notfound'">Секрет уже был просмотрен или истёк срок его хранения.</template>
                    <template v-else-if="state === 'badkey'">Ключ в ссылке не подходит. Секрет при этом уже удалён: попросите отправителя создать новый.</template>
                    <template v-else>Попробуйте обновить страницу.</template>
                </CardDescription>
            </CardHeader>
            <CardFooter>
                <Button as-child variant="outline"><Link href="/">Создать свой секрет</Link></Button>
            </CardFooter>
        </Card>
    </SecretLayout>
</template>
