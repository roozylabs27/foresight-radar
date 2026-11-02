import { useForm } from "@inertiajs/react";
import { CpuIcon, LoaderCircleIcon, SaveIcon } from "lucide-react";

import FormField from "@/Components/FormField";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Switch } from "@/Components/ui/switch";
import { cn } from "@/lib/utils";

const PROVIDER_LABELS = {
    anthropic: "Anthropic (Claude)",
    openai: "OpenAI",
    gemini: "Google Gemini",
    openai_compatible: "OpenAI-compatible",
};

const number = new Intl.NumberFormat("id-ID");

function UsageMeter({ usage, storageQuotaMb }) {
    const percent = usage.unlimited || usage.quota === 0 ? 0 : Math.min(100, Math.round((usage.used / usage.quota) * 100));

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-border/50 bg-muted/20 p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="text-[11px] font-medium text-muted-foreground uppercase">Token AI bulan ini ({usage.period})</span>
                <span className="text-sm font-semibold text-foreground">
                    {number.format(usage.used)}
                    <span className="font-normal text-muted-foreground">
                        {" "}/ {usage.unlimited ? "tanpa batas" : number.format(usage.quota)}
                    </span>
                </span>
            </div>
            {!usage.unlimited && (
                <div
                    role="progressbar"
                    aria-label="Pemakaian kuota token AI"
                    aria-valuenow={percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    className="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className={cn("h-full rounded-full", usage.exceeded ? "bg-destructive" : percent >= 80 ? "bg-warning" : "bg-primary")}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
            {usage.exceeded && (
                <p className="m-0 text-xs text-destructive">
                    Kuota habis. Fitur AI memakai mode tanpa AI sampai bulan berikutnya atau kuota paket dinaikkan.
                </p>
            )}
            <p className="m-0 text-[11px] text-muted-foreground">
                Kuota token ditetapkan oleh paket. Kuota penyimpanan paket: {number.format(storageQuotaMb)} MB (informasi paket, belum dibatasi).
            </p>
        </div>
    );
}

/**
 * Organization AI key and policy, plus monthly token usage (TENANT-008).
 * The stored key is never sent to the browser; an empty field keeps it.
 */
export default function OrganizationAiPanel({ ai, canManage }) {
    const setting = ai.setting;
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        provider: setting?.provider ?? "anthropic",
        model: setting?.model ?? "",
        api_key: "",
        clear_api_key: false,
        base_url: setting?.base_url ?? "",
        is_enabled: setting?.is_enabled ?? true,
        allow_user_byok: setting?.allow_user_byok ?? true,
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route("settings.organization.ai.update"), {
            preserveScroll: true,
            onSuccess: () => setData((values) => ({ ...values, api_key: "", clear_api_key: false })),
        });
    };

    const fieldError = (name) => (errors[name] ? { message: errors[name] } : undefined);

    return (
        <div className="rounded-xl border border-border bg-card p-6 shadow-2xs">
            <div className="mb-5 flex items-start gap-2.5 border-b border-border/60 pb-3">
                <CpuIcon aria-hidden="true" className="mt-0.5 size-4 text-primary" />
                <div>
                    <h3 className="text-sm font-semibold text-foreground">Mesin AI Organisasi</h3>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Urutan key: key pribadi anggota (jika diizinkan), lalu key organisasi, lalu key platform. Semua pemakaian dihitung ke kuota organisasi.
                    </p>
                </div>
            </div>

            <div className="flex max-w-xl flex-col gap-5">
                <UsageMeter usage={ai.usage} storageQuotaMb={ai.storage_quota_mb} />

                <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                    <fieldset disabled={!canManage || processing} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                        <FormField id="ai-provider" label="Penyedia" error={fieldError("provider")}>
                            <Select value={data.provider} onValueChange={(value) => setData("provider", value)}>
                                <SelectTrigger id="ai-provider" className="h-10 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent position="popper">
                                    {ai.providers.map((provider) => (
                                        <SelectItem key={provider} value={provider}>
                                            {PROVIDER_LABELS[provider] ?? provider}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField id="ai-model" label="Model" required={false} error={fieldError("model")}>
                            <Input id="ai-model" value={data.model} onChange={(e) => setData("model", e.target.value)} className="h-10 font-mono text-sm" />
                        </FormField>

                        {data.provider === "openai_compatible" && (
                            <FormField id="ai-base-url" label="Base URL" error={fieldError("base_url")}>
                                <Input
                                    id="ai-base-url"
                                    type="url"
                                    value={data.base_url}
                                    onChange={(e) => setData("base_url", e.target.value)}
                                    placeholder="https://openrouter.ai/api/v1"
                                    className="h-10 font-mono text-sm"
                                />
                            </FormField>
                        )}

                        <FormField id="ai-key" label="API key" required={false} error={fieldError("api_key")}>
                            <Input
                                id="ai-key"
                                type="password"
                                autoComplete="off"
                                value={data.api_key}
                                onChange={(e) => setData("api_key", e.target.value)}
                                placeholder={setting?.has_api_key ? "Tersimpan. Kosongkan untuk mempertahankan." : "Belum ada key organisasi"}
                                className="h-10 font-mono text-sm"
                            />
                            {setting?.has_api_key && (
                                <div className="flex items-center gap-2 pt-1">
                                    <Switch id="ai-clear-key" checked={data.clear_api_key} onCheckedChange={(value) => setData("clear_api_key", value)} />
                                    <Label htmlFor="ai-clear-key" className="text-xs font-normal">Hapus key tersimpan</Label>
                                </div>
                            )}
                        </FormField>

                        <div className="flex items-center gap-2">
                            <Switch id="ai-enabled" checked={data.is_enabled} onCheckedChange={(value) => setData("is_enabled", value)} />
                            <Label htmlFor="ai-enabled" className="text-sm font-normal">Gunakan key organisasi</Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Switch id="ai-byok" checked={data.allow_user_byok} onCheckedChange={(value) => setData("allow_user_byok", value)} />
                            <Label htmlFor="ai-byok" className="text-sm font-normal">Izinkan anggota memakai key pribadi</Label>
                        </div>
                    </fieldset>

                    {canManage ? (
                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing} aria-busy={processing || undefined} className="h-10 gap-2 px-5">
                                {processing ? <LoaderCircleIcon aria-hidden="true" className="size-4 animate-spin" /> : <SaveIcon aria-hidden="true" className="size-4" />}
                                <span>{processing ? "Menyimpan..." : "Simpan Pengaturan AI"}</span>
                            </Button>
                            {recentlySuccessful && <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Tersimpan.</span>}
                        </div>
                    ) : (
                        <p className="m-0 text-xs text-muted-foreground">Hanya admin organisasi yang dapat mengubah pengaturan AI.</p>
                    )}
                </form>
            </div>
        </div>
    );
}
