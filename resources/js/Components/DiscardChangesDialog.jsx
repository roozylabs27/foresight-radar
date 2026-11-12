import ConfirmDialog from "@/Components/ConfirmDialog";

/** Asked when a changed form is closed without saving (PRD-19). */
export default function DiscardChangesDialog({ open, onOpenChange, onDiscard }) {
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Buang perubahan?"
            description="Isian yang belum disimpan akan hilang."
            confirmText="Buang"
            cancelText="Lanjut mengisi"
            onConfirm={onDiscard}
        />
    );
}
