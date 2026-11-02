import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import SearchableSelect from "@/Components/SearchableSelect";

/**
 * Merge a duplicate driving force into another one (roadmap R1.3). `record` null closes the dialog;
 * `onMerge(targetId)` resolves to the 422 errors or undefined.
 */
export default function MergeForceDialog({ record, options, loading, onOpenChange, onMerge }) {
    const [target, setTarget] = useState("");
    const [errors, setErrors] = useState({});

    useEffect(() => {
        setTarget("");
        setErrors({});
    }, [record?.id]);

    const field = { name: "target", value: target, onChange: setTarget, onBlur: () => {} };
    const error = errors.target ? { message: [].concat(errors.target)[0] } : undefined;
    const merge = async () => {
        if (!target) return setErrors({ target: "Pilih driving force tujuan." });
        const result = await onMerge(target);
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title="Gabungkan driving force"
            description={record ? `"${record.keyword}" akan digabung ke driving force yang dipilih dan hilang dari daftar.` : undefined}
            open={record !== null}
            onOpenChange={onOpenChange}
            onOk={merge}
            btnText="Gabungkan"
            cancelText="Batal"
            loading={loading}
        >
            <div className="flex flex-col gap-4">
                <FormField id="merge-target" label="Gabungkan ke" error={error}>
                    <SearchableSelect
                        id="merge-target"
                        field={field}
                        fieldState={{ error }}
                        options={options.filter((option) => option.value !== record?.id)}
                        placeholder="Pilih driving force tujuan"
                    />
                </FormField>
                <ul className="m-0 flex list-disc flex-col gap-1 pl-4 text-xs text-muted-foreground">
                    <li>Sinyal, cross-impact dan kaitan di latihan scenario planning pindah ke driving force tujuan.</li>
                    <li>Bila keduanya ada di satu latihan, skor dan pengaturan driving force tujuan yang dipakai.</li>
                    <li>Skenario yang memakai driving force ini ditandai perlu ditinjau, tidak dihapus.</li>
                </ul>
            </div>
        </FormDialog>
    );
}
