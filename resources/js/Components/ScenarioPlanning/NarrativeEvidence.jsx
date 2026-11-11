/** The signals a framework's narratives may cite, with the S-label the text uses. */
export default function NarrativeEvidence({ evidence }) {
    if (!evidence?.length) {
        return <p className="m-0 text-sm text-muted-foreground">Belum ada sinyal ACCEPTED yang tertaut ke sumbu atau kondisi kerangka ini.</p>;
    }

    return (
        <ul className="m-0 flex list-none flex-col gap-2 p-0 text-xs">
            {evidence.map((item) => (
                <li key={item.ref} className="flex flex-col gap-0.5">
                    <span className="font-semibold text-foreground">
                        {item.ref} · {item.title}
                    </span>
                    {item.evidence_quote && <span className="text-muted-foreground italic">“{item.evidence_quote}”</span>}
                </li>
            ))}
        </ul>
    );
}
