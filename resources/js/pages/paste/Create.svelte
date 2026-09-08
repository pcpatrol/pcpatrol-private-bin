<script lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import Copy from '@lucide/svelte/icons/copy';
    import Flame from '@lucide/svelte/icons/flame';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { encodeKey, encryptPaste, generateKey, type PasteFormat } from '@/lib/paste-crypto';
    import { toUrl } from '@/lib/utils';
    import { store } from '@/routes/paste';

    let {
        expiryOptions,
        defaultExpiry,
        maxPayloadBytes,
    }: {
        expiryOptions: string[];
        defaultExpiry: string;
        maxPayloadBytes: number;
    } = $props();

    type StoreResponse = {
        id: string;
        url: string;
        deleteUrl: string;
        expiresAt: string | null;
    };

    const expiryLabels: Record<string, string> = {
        '5min': '5 minuten',
        '1hour': '1 uur',
        '1day': '1 dag',
        '1week': '1 week',
        '1month': '1 maand',
        '1year': '1 jaar',
        never: 'Nooit',
    };

    const formatLabels: Record<PasteFormat, string> = {
        plaintext: 'Platte tekst',
        markdown: 'Markdown',
        code: 'Code',
    };

    const http = useHttp<
        {
            payload: string;
            format: PasteFormat;
            has_password: boolean;
            burn_after_reading: boolean;
            expires_in: string;
        },
        StoreResponse
    >({
        payload: '',
        format: 'plaintext',
        has_password: false,
        burn_after_reading: false,
        expires_in: defaultExpiry,
    });

    let text = $state('');
    let format = $state<PasteFormat>('plaintext');
    let expiresIn = $state(defaultExpiry);
    let burnAfterReading = $state(false);
    let password = $state('');
    let busy = $state(false);
    let failure = $state('');
    let result = $state<{ shareUrl: string; deleteUrl: string } | null>(null);
    let copied = $state(false);

    const canSubmit = $derived(text.trim().length > 0 && !busy);

    async function submit(event: SubmitEvent): Promise<void> {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        busy = true;
        failure = '';

        try {
            const key = generateKey();
            const payload = await encryptPaste(text, key, password, format, burnAfterReading);

            if (payload.length > maxPayloadBytes) {
                failure = 'Deze paste is te groot. Kort de tekst in en probeer het opnieuw.';

                return;
            }

            http.setStore({
                payload,
                format,
                has_password: password.length > 0,
                burn_after_reading: burnAfterReading,
                expires_in: expiresIn,
            });

            const response = await http.post(toUrl(store()));

            result = {
                shareUrl: `${response.url}#${encodeKey(key)}`,
                deleteUrl: response.deleteUrl,
            };
            text = '';
            password = '';
        } catch (error) {
            failure =
                error instanceof Error
                    ? error.message
                    : 'Het opslaan is niet gelukt. Probeer het opnieuw.';
        } finally {
            busy = false;
        }
    }

    async function copyShareUrl(): Promise<void> {
        if (result === null) {
            return;
        }

        await navigator.clipboard.writeText(result.shareUrl);
        copied = true;
        setTimeout(() => (copied = false), 2000);
    }

    function startOver(): void {
        result = null;
        failure = '';
        copied = false;
    }
</script>

<AppHead title="Nieuwe paste" />

{#if result}
    <section class="space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold tracking-tight">Je paste staat klaar</h1>
            <p class="text-sm text-muted-foreground">
                Deel de link hieronder. De sleutel zit erin verwerkt, dus wie de link heeft kan
                de inhoud lezen. Bewaar hem zorgvuldig.
            </p>
        </div>

        <div class="rounded-card border border-border bg-card p-5 shadow-sm">
            <Label class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Deel-link
            </Label>
            <div class="mt-2 flex gap-2">
                <Input
                    readonly
                    value={result.shareUrl}
                    class="font-mono text-xs"
                    onclick={(event: Event) => (event.currentTarget as HTMLInputElement).select()}
                />
                <Button onclick={copyShareUrl} class="shrink-0">
                    {#if copied}
                        <Check class="size-4" />
                        Gekopieerd
                    {:else}
                        <Copy class="size-4" />
                        Kopieer
                    {/if}
                </Button>
            </div>
        </div>

        <div class="rounded-card border border-border bg-secondary/50 p-5">
            <Label class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Verwijderlink
            </Label>
            <p class="mt-1 text-sm text-muted-foreground">
                Alleen jij hebt deze link. Hiermee haal je de paste weg voordat hij vanzelf
                verloopt.
            </p>
            <Input readonly value={result.deleteUrl} class="mt-3 font-mono text-xs" />
        </div>

        <Button variant="outline" onclick={startOver}>Nog een paste maken</Button>
    </section>
{:else}
    <section class="space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold tracking-tight">Deel iets vertrouwelijks</h1>
            <p class="text-sm text-muted-foreground">
                Typ je tekst, kies hoelang hij mag blijven staan en deel de link. De versleuteling
                gebeurt hier in je browser.
            </p>
        </div>

        <form onsubmit={submit} class="space-y-6">
            <div class="space-y-2">
                <Label for="paste-text">Tekst</Label>
                <textarea
                    id="paste-text"
                    bind:value={text}
                    rows="14"
                    spellcheck="false"
                    placeholder="Plak of typ hier wat je wilt delen..."
                    class="flex w-full rounded-md border border-input bg-card px-3 py-2 font-mono text-sm shadow-sm transition-colors placeholder:font-sans placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                ></textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="paste-expiry">Verloopt na</Label>
                    <select
                        id="paste-expiry"
                        bind:value={expiresIn}
                        class="flex h-9 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {#each expiryOptions as option (option)}
                            <option value={option}>{expiryLabels[option] ?? option}</option>
                        {/each}
                    </select>
                </div>

                <div class="space-y-2">
                    <Label for="paste-format">Weergave</Label>
                    <select
                        id="paste-format"
                        bind:value={format}
                        class="flex h-9 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {#each Object.entries(formatLabels) as [value, label] (value)}
                            <option {value}>{label}</option>
                        {/each}
                    </select>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {#if format === 'markdown'}
                    Standaard Markdown, dus let op de spatie:
                    <code class="rounded bg-secondary px-1 py-0.5 font-mono">### Kop</code>,
                    <code class="rounded bg-secondary px-1 py-0.5 font-mono">**vet**</code>
                    of
                    <code class="rounded bg-secondary px-1 py-0.5 font-mono">- lijst</code>.
                {:else if format === 'code'}
                    De programmeertaal wordt automatisch herkend en gekleurd.
                {:else}
                    De tekst wordt precies zo getoond als je hem typt.
                {/if}
            </p>

            <div class="space-y-2">
                <Label for="paste-password">Extra wachtwoord (optioneel)</Label>
                <Input
                    id="paste-password"
                    type="password"
                    bind:value={password}
                    autocomplete="new-password"
                    placeholder="Laat leeg om alleen de link te gebruiken"
                />
                <p class="text-xs text-muted-foreground">
                    Wie de paste opent moet dit wachtwoord ook weten. Deel het via een ander
                    kanaal dan de link zelf.
                </p>
            </div>

            <div
                class="flex items-start gap-3 rounded-card border border-border bg-secondary/50 p-5"
            >
                <Checkbox id="paste-burn" bind:checked={burnAfterReading} class="mt-0.5" />
                <div class="space-y-1">
                    <Label for="paste-burn" class="flex items-center gap-1.5">
                        <Flame class="size-3.5 text-warn" />
                        Vernietigen na lezen
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        De paste wordt onherroepelijk verwijderd zodra iemand hem opent. Ook jij
                        kunt hem daarna niet meer bekijken.
                    </p>
                </div>
            </div>

            {#if failure}
                <div
                    class="flex items-start gap-2 rounded-card border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{failure}</span>
                </div>
            {/if}

            <Button type="submit" size="lg" disabled={!canSubmit}>
                {busy ? 'Bezig met versleutelen...' : 'Versleutelen en link maken'}
            </Button>
        </form>
    </section>
{/if}
