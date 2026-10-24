import { useState } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircleIcon,
    BotIcon,
    CheckCircle2Icon,
    CheckIcon,
    ExternalLinkIcon,
    EyeIcon,
    EyeOffIcon,
    Loader2Icon,
    PlayIcon,
    RotateCcwIcon,
    SaveIcon,
    ZapIcon,
} from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Switch } from "@/Components/ui/switch";
import { cn } from "@/lib/utils";

export default function ProviderCard({ providerData }) {
    const {
        provider,
        label,
        description,
        default_model,
        models = [],
        needs_base_url,
        default_base_url,
        key_placeholder,
        doc_url,
        is_configured,
        is_active,
        configured_model,
        configured_base_url,
        masked_api_key,
    } = providerData;

    const [selectedModel, setSelectedModel] = useState(configured_model || default_model);
    const [isCustomModel, setIsCustomModel] = useState(!models.includes(configured_model || default_model));
    const [customModelInput, setCustomModelInput] = useState(!models.includes(configured_model || default_model) ? configured_model : "");
    const [apiKey, setApiKey] = useState("");
    const [showKey, setShowKey] = useState(false);
    const [baseUrl, setBaseUrl] = useState(configured_base_url || default_base_url || "");
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [saving, setSaving] = useState(false);
    const [toggling, setToggling] = useState(false);

    const effectiveModel = isCustomModel ? customModelInput.trim() : selectedModel;

    const handleModelSelectChange = (value) => {
        if (value === "__custom__") {
            setIsCustomModel(true);
        } else {
            setIsCustomModel(false);
            setSelectedModel(value);
        }
        setTestResult(null);
    };

    const handleTestConnection = async () => {
        if (!effectiveModel) {
            toast.error("Pilih atau masukkan nama model terlebih dahulu.");
            return;
        }

        if (!apiKey && !is_configured) {
            toast.error("Masukkan API Key untuk melakukan pengujian.");
            return;
        }

        setTesting(true);
        setTestResult(null);

        try {
            const response = await axios.post(route("settings.llm.test"), {
                provider,
                model: effectiveModel,
                api_key: apiKey || null,
                base_url: baseUrl || null,
            });

            setTestResult(response.data);
            if (response.data.success) {
                toast.success(`Koneksi ke ${label} berhasil (${response.data.latency_ms} ms)`);
            } else {
                toast.error(response.data.message || "Pengujian koneksi gagal.");
            }
        } catch (error) {
            const msg = error.response?.data?.message ?? error.message;
            setTestResult({ success: false, message: msg });
            toast.error("Gagal menguji koneksi", { description: msg });
        } finally {
            setTesting(false);
        }
    };

    const handleSave = (e) => {
        e.preventDefault();
        if (!effectiveModel) {
            toast.error("Nama model wajib diisi.");
            return;
        }

        setSaving(true);
        router.post(
            route("settings.llm.store"),
            {
                provider,
                model: effectiveModel,
                api_key: apiKey || null,
                base_url: baseUrl || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(false);
                    setApiKey("");
                    toast.success(`Pengaturan ${label} berhasil disimpan.`);
                },
                onError: (errors) => {
                    setSaving(false);
                    toast.error("Gagal menyimpan pengaturan", {
                        description: Object.values(errors)[0] || "Terjadi kesalahan.",
                    });
                },
            }
        );
    };

    const handleToggleActive = (checked) => {
        if (checked && !is_configured && !apiKey) {
            toast.error("Simpan API Key terlebih dahulu sebelum mengaktifkan provider ini.");
            return;
        }

        setToggling(true);
        router.post(
            route("settings.llm.activate"),
            {
                provider,
                active: checked,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setToggling(false);
                    toast.success(checked ? `${label} diaktifkan sebagai provider utama.` : `${label} dinonaktifkan.`);
                },
                onError: (errors) => {
                    setToggling(false);
                    toast.error(Object.values(errors)[0] || "Gagal mengubah status aktif.");
                },
            }
        );
    };

    const handleReset = () => {
        if (!confirm(`Hapus dan reset konfigurasi ${label}?`)) return;

        router.delete(route("settings.llm.destroy", { provider }), {
            preserveScroll: true,
            onSuccess: () => {
                setApiKey("");
                setTestResult(null);
                toast.success(`Konfigurasi ${label} telah direset.`);
            },
        });
    };

    return (
        <Card className={cn("flex flex-col transition-all", is_active && "border-primary ring-1 ring-primary/40 shadow-sm")}>
            <CardHeader className="pb-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                        <div className={cn("flex size-9 items-center justify-center rounded-lg", is_active ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
                            {is_active ? <ZapIcon className="size-4" /> : <BotIcon className="size-4" />}
                        </div>
                        <div>
                            <CardTitle className="text-base font-semibold">{label}</CardTitle>
                            <CardDescription className="text-xs">{description}</CardDescription>
                        </div>
                    </div>
                    {is_active ? (
                        <Badge variant="default" className="gap-1 bg-emerald-600 text-white hover:bg-emerald-600 dark:bg-emerald-700">
                            <CheckIcon className="size-3" />
                            Aktif
                        </Badge>
                    ) : is_configured ? (
                        <Badge variant="secondary" className="text-xs">Tersimpan</Badge>
                    ) : (
                        <Badge variant="outline" className="text-xs text-muted-foreground">Belum Diatur</Badge>
                    )}
                </div>
            </CardHeader>

            <CardContent className="flex flex-1 flex-col gap-4 text-sm">
                {/* Active switch toggle */}
                <div className="flex items-center justify-between rounded-lg border border-border/80 bg-muted/40 p-3">
                    <div className="flex flex-col">
                        <span className="text-xs font-semibold text-foreground">Gunakan Sebagai Provider Utama</span>
                        <span className="text-[11px] text-muted-foreground">
                            {is_active ? "Provider ini saat ini aktif digunakan untuk ekstraksi & skenario." : "Aktifkan provider ini untuk akun Anda (hanya 1 provider aktif)."}
                        </span>
                    </div>
                    <Switch
                        checked={is_active}
                        onCheckedChange={handleToggleActive}
                        disabled={toggling || (!is_configured && !apiKey)}
                        aria-label={`Aktifkan ${label}`}
                    />
                </div>

                {/* Model selection */}
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`model-${provider}`} className="text-xs font-medium">
                        Model
                    </Label>
                    {!isCustomModel ? (
                        <Select value={selectedModel} onValueChange={handleModelSelectChange}>
                            <SelectTrigger id={`model-${provider}`} className="h-9 text-xs">
                                <SelectValue placeholder="Pilih model..." />
                            </SelectTrigger>
                            <SelectContent>
                                {models.map((m) => (
                                    <SelectItem key={m} value={m} className="text-xs">
                                        {m} {m === default_model && "(Default)"}
                                    </SelectItem>
                                ))}
                                <SelectItem value="__custom__" className="text-xs font-medium text-primary">
                                    + Custom Model Lainnya...
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    ) : (
                        <div className="flex gap-2">
                            <Input
                                id={`model-${provider}`}
                                value={customModelInput}
                                onChange={(e) => setCustomModelInput(e.target.value)}
                                placeholder="Contoh: gemini-3.8-flash atau claude-sonnet-5-5"
                                className="h-9 text-xs"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setIsCustomModel(false)}
                                className="h-9 text-xs shrink-0"
                            >
                                Daftar Pilihan
                            </Button>
                        </div>
                    )}
                </div>

                {/* API Key */}
                <div className="flex flex-col gap-1.5">
                    <div className="flex items-center justify-between">
                        <Label htmlFor={`key-${provider}`} className="text-xs font-medium">
                            API Key
                        </Label>
                        {doc_url && (
                            <a
                                href={doc_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-[11px] text-primary hover:underline"
                            >
                                <span>Dapatkan Key</span>
                                <ExternalLinkIcon className="size-3" />
                            </a>
                        )}
                    </div>
                    <div className="relative">
                        <Input
                            id={`key-${provider}`}
                            type={showKey ? "text" : "password"}
                            value={apiKey}
                            onChange={(e) => setApiKey(e.target.value)}
                            placeholder={is_configured ? `Tersimpan: ${masked_api_key}` : key_placeholder}
                            className="h-9 pr-9 text-xs font-mono"
                            autoComplete="off"
                        />
                        <button
                            type="button"
                            onClick={() => setShowKey(!showKey)}
                            aria-label={showKey ? "Sembunyikan API Key" : "Tampilkan API Key"}
                            className="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                        >
                            {showKey ? <EyeOffIcon className="size-3.5" /> : <EyeIcon className="size-3.5" />}
                        </button>
                    </div>
                    {is_configured && !apiKey && (
                        <p className="text-[11px] text-muted-foreground">
                            Kosongkan jika tidak ingin mengubah API Key yang tersimpan.
                        </p>
                    )}
                </div>

                {/* Base URL (if custom or required) */}
                {(needs_base_url || provider === "gemini" || provider === "openai") && (
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`url-${provider}`} className="text-xs font-medium">
                            Base URL {needs_base_url ? "(Wajib)" : "(Opsional / Endpoint Custom)"}
                        </Label>
                        <Input
                            id={`url-${provider}`}
                            value={baseUrl}
                            onChange={(e) => setBaseUrl(e.target.value)}
                            placeholder={default_base_url || "https://api.openai.com/v1"}
                            className="h-9 text-xs font-mono"
                        />
                    </div>
                )}

                {/* Connection Test Result */}
                {testResult && (
                    <div
                        className={cn(
                            "flex items-start gap-2 rounded-md p-2.5 text-xs",
                            testResult.success
                                ? "bg-emerald-50 text-emerald-900 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                                : "bg-rose-50 text-rose-900 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800"
                        )}
                    >
                        {testResult.success ? (
                            <CheckCircle2Icon className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" />
                        ) : (
                            <AlertCircleIcon className="size-4 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                        )}
                        <div className="flex flex-col gap-0.5 min-w-0">
                            <span className="font-semibold">{testResult.success ? "Koneksi Berhasil" : "Koneksi Gagal"}</span>
                            <span className="text-[11px] break-words">{testResult.message}</span>
                            {testResult.latency_ms && (
                                <span className="text-[10px] opacity-80">Latensi respons: {testResult.latency_ms} ms</span>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>

            <CardFooter className="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleTestConnection}
                    disabled={testing || saving || (!is_configured && !apiKey)}
                    className="h-8 gap-1.5 text-xs"
                >
                    {testing ? <Loader2Icon className="size-3.5 animate-spin" /> : <PlayIcon className="size-3.5" />}
                    <span>Test Koneksi</span>
                </Button>

                <div className="flex items-center gap-2">
                    {is_configured && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                            className="h-8 gap-1 text-xs text-muted-foreground hover:text-destructive"
                            title="Reset konfigurasi provider ini"
                        >
                            <RotateCcwIcon className="size-3.5" />
                            <span className="sr-only sm:not-sr-only">Reset</span>
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        onClick={handleSave}
                        disabled={saving}
                        className="h-8 gap-1.5 text-xs"
                    >
                        {saving ? <Loader2Icon className="size-3.5 animate-spin" /> : <SaveIcon className="size-3.5" />}
                        <span>Simpan</span>
                    </Button>
                </div>
            </CardFooter>
        </Card>
    );
}

