<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LockKeyhole } from 'lucide-vue-next';

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-screen flex-col bg-muted/40">
        <header class="mx-auto flex w-full max-w-2xl items-center gap-2 px-4 py-6">
            <Link href="/" class="flex items-center gap-2 text-lg font-semibold">
                <LockKeyhole class="size-5" />
                Secret
            </Link>
        </header>
        <main class="mx-auto w-full max-w-2xl flex-1 px-4 pb-10">
            <slot />
        </main>
        <footer class="mx-auto w-full max-w-2xl px-4 py-6 text-xs text-muted-foreground">
            Данные шифруются в вашем браузере (AES-256-GCM). Ключ передаётся только в ссылке после «#» и не попадает на сервер.
            Секрет удаляется после первого просмотра или по истечении срока.
        </footer>
    </div>
</template>
