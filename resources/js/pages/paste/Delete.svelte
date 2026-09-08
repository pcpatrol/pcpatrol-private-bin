<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { destroy as destroyRoute, show as showRoute } from '@/routes/paste';

    let { pasteId, token }: { pasteId: string; token: string } = $props();

    let busy = $state(false);

    function destroy(): void {
        busy = true;

        router.delete(toUrl(destroyRoute(pasteId)), {
            data: { token },
            onFinish: () => (busy = false),
        });
    }
</script>

<AppHead title="Paste verwijderen" />

<section class="space-y-6">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Deze paste verwijderen?</h1>
        <p class="text-sm text-muted-foreground">
            Daarna is de inhoud weg en werkt de deel-link niet meer. Dit kan niet ongedaan
            worden gemaakt.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <Button variant="destructive" onclick={destroy} disabled={busy}>
            <Trash2 class="size-4" />
            {busy ? 'Bezig...' : 'Definitief verwijderen'}
        </Button>
        <Link
            href={toUrl(showRoute(pasteId))}
            class="text-sm font-medium text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
        >
            Annuleren
        </Link>
    </div>
</section>
