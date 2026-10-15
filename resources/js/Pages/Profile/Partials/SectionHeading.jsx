import { cn } from "@/lib/utils";

/** Title and description at the top of a profile card. */
export default function SectionHeading({ id, title, description, className }) {
    return (
        <div className="mb-5 flex flex-col gap-1">
            <h2 id={id} className={cn("m-0 text-base font-semibold text-foreground", className)}>{title}</h2>
            <p className="m-0 text-[13px] text-muted-foreground">{description}</p>
        </div>
    );
}
