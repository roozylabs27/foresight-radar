import { useState } from "react";
import { Head } from "@inertiajs/react";
import { LayersIcon, PlusIcon } from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
import DimensionDialog from "@/Components/Settings/DimensionDialog";
import EnvironmentDialog from "@/Components/Settings/EnvironmentDialog";
import EnvironmentSection from "@/Components/Settings/EnvironmentSection";
import { Button } from "@/Components/ui/button";
import { useDimensionSettings } from "@/hooks/use-dimension-settings";

/**
 * Settings → Dimensi (plan part B2): the organisation's own dimensions grouped by environment.
 * Order and colours here are what the radar, filters and forms show.
 */
export default function Dimensions({ auth, environments = [], errors = {}, title = "Dimensi & Lingkungan" }) {
    const settings = useDimensionSettings();
    const [dimensionTarget, setDimensionTarget] = useState(null);
    const [environmentTarget, setEnvironmentTarget] = useState(null);

    const actions = {
        ...settings,
        addDimension: (environmentId) => setDimensionTarget({ dimension: null, environmentId }),
        editDimension: (dimension, environmentId) => setDimensionTarget({ dimension, environmentId }),
        editEnvironment: (environment) => setEnvironmentTarget({ environment }),
    };
    const problem = errors.dimension ?? errors.environment;

    return (
        <SettingsLayout auth={auth} title={title} activeTab="dimensions">
            <Head title={title} />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-start gap-3">
                    <div className="flex min-w-0 flex-1 items-start gap-2.5">
                        <LayersIcon aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-primary" />
                        <p className="m-0 text-sm text-muted-foreground">
                            Dimensi mengelompokkan driving force dan sinyal organisasi Anda. Urutan dan warna di sini dipakai radar, filter dan formulir. Dimensi yang
                            sudah dipakai tidak bisa dihapus; arsipkan agar tidak dipilih untuk data baru.
                        </p>
                    </div>
                    <Button type="button" variant="outline" onClick={() => setEnvironmentTarget({ environment: null })}>
                        <PlusIcon aria-hidden="true" />
                        Lingkungan
                    </Button>
                </div>
                {problem && <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{[].concat(problem)[0]}</p>}

                {environments.map((environment, index) => (
                    <EnvironmentSection
                        key={environment.id}
                        environment={environment}
                        first={index === 0}
                        last={index === environments.length - 1}
                        busy={settings.busy}
                        actions={actions}
                    />
                ))}
            </div>

            <DimensionDialog
                target={dimensionTarget}
                environments={environments}
                loading={settings.busy}
                onOpenChange={(open) => !open && setDimensionTarget(null)}
                onSave={(values) => settings.saveDimension(values, dimensionTarget?.dimension?.id)}
            />
            <EnvironmentDialog
                target={environmentTarget}
                loading={settings.busy}
                onOpenChange={(open) => !open && setEnvironmentTarget(null)}
                onSave={(values) => settings.saveEnvironment(values, environmentTarget?.environment?.id)}
            />
        </SettingsLayout>
    );
}
