<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { home } from '@/routes';
    import { toUrl } from '@/lib/utils';

    let { children }: { children: Snippet } = $props();
</script>

<div class="flex min-h-screen flex-col bg-background text-foreground">
    <header class="bg-ink">
        <div
            class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-6 py-4"
        >
            <Link
                href={toUrl(home())}
                class="flex items-center gap-3 transition-opacity hover:opacity-80"
            >
                <img
                    src="/images/pcpatrol-logo-wit.png"
                    alt="PC Patrol"
                    width="1045"
                    height="143"
                    class="h-6 w-auto"
                />
                <span aria-hidden="true" class="h-5 w-px bg-on-dark-muted/40"></span>
                <span class="text-sm leading-none font-medium text-on-dark-strong">
                    Private Bin
                </span>
            </Link>

            <Link
                href={toUrl(home())}
                class="text-sm font-medium text-on-dark transition-colors hover:text-white"
            >
                Nieuwe paste
            </Link>
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl grow px-6 py-10">
        {@render children()}
    </main>

    <footer class="border-t border-border">
        <div
            class="mx-auto w-full max-w-3xl px-6 py-6 text-xs leading-relaxed text-muted-foreground"
        >
            Alles wordt in je browser versleuteld. De sleutel staat achter het
            <code class="font-mono text-[11px] text-foreground">#</code> in de link en wordt
            nooit naar de server gestuurd, dus wij kunnen je paste niet lezen.
        </div>
    </footer>
</div>
