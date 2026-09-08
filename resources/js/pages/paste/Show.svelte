<script lang="ts">
    import { onMount } from 'svelte';
    import { Link, useHttp } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import Copy from '@lucide/svelte/icons/copy';
    import Flame from '@lucide/svelte/icons/flame';
    import LockKeyhole from '@lucide/svelte/icons/lock-keyhole';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        DecryptionError,
        decodeKey,
        decryptPaste,
        type PasteFormat,
        type PasteKey,
    } from '@/lib/paste-crypto';
    import { renderPaste } from '@/lib/paste-render';
    import { toUrl } from '@/lib/utils';
    import { home } from '@/routes';
    import { reveal as revealRoute } from '@/routes/paste';

    type PasteProps = {
        id: string;
        format: PasteFormat;
        hasPassword: boolean;
        burnAfterReading: boolean;
        expiresAt: string | null;
        payload: string | null;
    };

    let { paste }: { paste: PasteProps } = $props();

    const http = useHttp<Record<string, never>, { payload: string }>({});

    let key = $state<PasteKey | null>(null);
    let password = $state('');
    let content = $state<string | null>(null);
    let error = $state('');
    let busy = $state(false);
    let burned = $state(false);
    let copied = $state(false);
    let revealedPayload = $state<string | null>(null);

    const awaitingConfirmation = $derived(
        paste.burnAfterReading && !burned && content === null && error === '',
    );
    const awaitingPassword = $derived(
        !awaitingConfirmation && paste.hasPassword && content === null && error === '',
    );

    onMount(() => {
        try {
            key = decodeKey(window.location.hash.replace(/^#/, ''));
        } catch {
            error =
                'Deze link mist de sleutel. Waarschijnlijk is alleen het deel voor het #-teken gekopieerd.';

            return;
        }

        if (!paste.burnAfterReading && !paste.hasPassword) {
            void decrypt(paste.payload);
        }
    });

    async function decrypt(payload: string | null): Promise<void> {
        if (key === null || payload === null) {
            error = 'Deze paste kan niet worden gelezen.';

            return;
        }

        busy = true;

        try {
            content = await decryptPaste(
                payload,
                key,
                password,
                paste.format,
                paste.burnAfterReading,
            );
            error = '';
        } catch (failure) {
            error =
                failure instanceof DecryptionError
                    ? paste.hasPassword
                        ? 'Ontsleutelen mislukt. Klopt het wachtwoord?'
                        : failure.message
                    : 'Ontsleutelen mislukt.';
        } finally {
            busy = false;
        }
    }

    async function reveal(): Promise<void> {
        busy = true;

        try {
            const response = await http.post(toUrl(revealRoute(paste.id)));
            burned = true;

            if (!paste.hasPassword) {
                await decrypt(response.payload);
            } else {
                revealedPayload = response.payload;
            }
        } catch {
            error = 'Deze paste bestaat niet meer.';
        } finally {
            busy = false;
        }
    }

    async function submitPassword(event: SubmitEvent): Promise<void> {
        event.preventDefault();
        await decrypt(revealedPayload ?? paste.payload);
    }

    async function copyContent(): Promise<void> {
        if (content === null) {
            return;
        }

        await navigator.clipboard.writeText(content);
        copied = true;
        setTimeout(() => (copied = false), 2000);
    }

    const rendered = $derived(
        content === null ? null : renderPaste(content, paste.format),
    );

    const formatLabels: Record<PasteFormat, string> = {
        plaintext: 'Platte tekst',
        markdown: 'Markdown',
        code: 'Code',
    };

    const expiryLabel = $derived(
        paste.expiresAt === null
            ? 'Blijft staan tot hij wordt verwijderd'
            : `Verloopt op ${new Date(paste.expiresAt).toLocaleString('nl-NL')}`,
    );
</script>

<AppHead title="Paste" />

<section class="space-y-6">
    {#if error}
        <div
            class="flex items-start gap-2 rounded-card border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <span>{error}</span>
        </div>
    {/if}

    {#if awaitingConfirmation}
        <div class="space-y-4 rounded-card border border-warn/40 bg-warn/10 p-5">
            <div class="flex items-center gap-2">
                <Flame class="size-5 text-warn" />
                <h1 class="text-lg font-semibold tracking-tight">
                    Deze paste wordt vernietigd zodra je hem opent
                </h1>
            </div>
            <p class="text-sm text-muted-foreground">
                Je kunt hem daarna niet nog eens bekijken, en niemand anders ook. Zorg dat je de
                inhoud meteen kunt overnemen.
            </p>
            <Button onclick={reveal} disabled={busy}>
                {busy ? 'Bezig...' : 'Openen en vernietigen'}
            </Button>
        </div>
    {:else if awaitingPassword}
        <div class="space-y-4 rounded-card border border-border bg-card p-5">
            <div class="flex items-center gap-2">
                <LockKeyhole class="size-5 text-muted-foreground" />
                <h1 class="text-lg font-semibold tracking-tight">
                    Deze paste is met een wachtwoord beveiligd
                </h1>
            </div>
            <form onsubmit={submitPassword} class="space-y-4">
                <Label for="paste-password">Wachtwoord</Label>
                <Input
                    id="paste-password"
                    type="password"
                    bind:value={password}
                    autocomplete="off"
                />
                <Button type="submit" disabled={busy || password.length === 0}>
                    {busy ? 'Bezig met ontsleutelen...' : 'Ontsleutelen'}
                </Button>
            </form>
        </div>
    {:else if content !== null}
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Paste</h1>
                <p class="mt-1 text-xs text-muted-foreground">
                    {formatLabels[paste.format] ?? paste.format}
                    &middot;
                    {#if burned}
                        Deze paste is zojuist vernietigd en bestaat alleen nog op dit scherm.
                    {:else}
                        {expiryLabel}
                    {/if}
                </p>
            </div>
            <Button variant="outline" size="sm" onclick={copyContent}>
                {#if copied}
                    <Check class="size-4" />
                    Gekopieerd
                {:else}
                    <Copy class="size-4" />
                    Kopieer
                {/if}
            </Button>
        </div>

        {#if rendered !== null}
            <!-- eslint-disable-next-line svelte/no-at-html-tags -- sanitised in paste-render -->
            <div
                class="paste-rendered overflow-x-auto rounded-card border border-border bg-card p-5 text-sm leading-relaxed"
            >
                {@html rendered}
            </div>
        {:else}
            <pre
                class="overflow-x-auto rounded-card border border-border bg-card p-5 font-mono text-sm leading-relaxed whitespace-pre-wrap">{content}</pre>
        {/if}
    {:else if !error}
        <div class="space-y-4">
            <div class="h-6 w-40 animate-pulse rounded bg-muted"></div>
            <div class="h-40 animate-pulse rounded-card bg-muted"></div>
        </div>
    {/if}

    <Link
        href={toUrl(home())}
        class="inline-block text-sm font-medium text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
    >
        Zelf een paste maken
    </Link>
</section>
