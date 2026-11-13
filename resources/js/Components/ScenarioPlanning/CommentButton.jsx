import { useEffect, useState } from "react";
import { usePage } from "@inertiajs/react";
import { CircleCheckIcon, MessageSquareIcon, RotateCcwIcon, Trash2Icon } from "lucide-react";

import { Button } from "@/Components/ui/button";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/Components/ui/sheet";
import { Textarea } from "@/Components/ui/textarea";
import { useComments } from "@/hooks/use-comments";
import { cn } from "@/lib/utils";

const when = (iso) => new Date(iso).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" });

function Thread({ projectId, type, id }) {
    const comments = useComments(projectId, type, id);
    const [body, setBody] = useState("");
    const [error, setError] = useState(null);

    const { load } = comments;
    useEffect(() => {
        load();
    }, [load]);

    const add = async () => {
        const errors = await comments.add(body);
        if (errors) return setError(errors.body ?? errors.id ?? errors.type);
        setError(null);
        setBody("");
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3 p-4">
            {comments.loading && comments.thread.comments.length === 0 ? (
                <p className="m-0 text-sm text-muted-foreground">Memuat diskusi…</p>
            ) : comments.thread.comments.length === 0 ? (
                <p className="m-0 text-sm text-muted-foreground">Belum ada komentar.</p>
            ) : (
                <ol className="m-0 flex min-h-0 list-none flex-col gap-3 overflow-y-auto p-0">
                    {comments.thread.comments.map((comment) => (
                        <li key={comment.id} className={cn("flex flex-col gap-1 rounded-md border border-border p-3", comment.resolved_at && "bg-muted/40")}>
                            <span className="text-xs text-muted-foreground">
                                {comment.author ?? "Pengguna dihapus"} · {when(comment.at)}
                            </span>
                            <p className="m-0 text-sm whitespace-pre-line text-foreground">{comment.body}</p>
                            {comment.resolved_at && <span className="text-xs text-muted-foreground">Selesai oleh {comment.resolved_by}</span>}
                            {(comment.can_resolve || comment.can_delete) && (
                                <div className="flex gap-1">
                                    {comment.can_resolve && (
                                        <Button size="sm" variant="ghost" disabled={comments.busy} onClick={() => comments.resolve(comment.id)}>
                                            {comment.resolved_at ? <RotateCcwIcon aria-hidden="true" /> : <CircleCheckIcon aria-hidden="true" />}
                                            {comment.resolved_at ? "Buka lagi" : "Tandai selesai"}
                                        </Button>
                                    )}
                                    {comment.can_delete && (
                                        <Button size="sm" variant="ghost" className="text-destructive" disabled={comments.busy} onClick={() => comments.remove(comment.id)}>
                                            <Trash2Icon aria-hidden="true" />
                                            Hapus
                                        </Button>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {comments.thread.can_comment ? (
                <div className="mt-auto flex flex-col gap-2 border-t border-border pt-3">
                    <Textarea value={body} onChange={(event) => setBody(event.target.value)} maxLength={2000} rows={3} placeholder="Tulis komentar" aria-label="Komentar baru" />
                    {error && <p role="alert" className="m-0 text-xs text-danger">{[].concat(error)[0]}</p>}
                    <Button size="sm" className="self-end" disabled={!body.trim() || comments.busy} onClick={add}>
                        Kirim
                    </Button>
                </div>
            ) : (
                !comments.loading && <p className="mt-auto m-0 text-xs text-muted-foreground">Anda dapat membaca diskusi ini, tetapi hanya peserta yang berkontribusi yang dapat berkomentar.</p>
            )}
        </div>
    );
}

/**
 * Opens the comment thread of one exercise artefact (roadmap R2.2) in a side sheet. `type` is
 * force, uncertainty, quadrant or response; `count` is the number of open comments.
 */
export default function CommentButton({ type, id, title, count = 0 }) {
    const { project } = usePage().props;
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button size="sm" variant="ghost" onClick={() => setOpen(true)} aria-label={`Diskusi ${title}${count ? `, ${count} komentar terbuka` : ""}`}>
                <MessageSquareIcon aria-hidden="true" />
                {count > 0 ? count : "Diskusi"}
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-md">
                    <SheetHeader className="border-b pr-12">
                        <SheetTitle className="text-base">{title}</SheetTitle>
                        <SheetDescription>Diskusi tim tentang item ini.</SheetDescription>
                    </SheetHeader>
                    {open && <Thread projectId={project.id} type={type} id={id} />}
                </SheetContent>
            </Sheet>
        </>
    );
}
