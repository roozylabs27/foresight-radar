import { useState } from "react";
import dayjs from "dayjs";
import { id as idLocale } from "react-day-picker/locale";
import { CalendarOutlined } from "@ant-design/icons";

import { Button } from "@/Components/ui/button";
import { Calendar } from "@/Components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { useMediaQuery } from "@/hooks/use-media-query";
import { cn } from "@/lib/utils";

const QUERY_FORMAT = "YYYY-MM-DD";

function toDate(value) {
    if (!value) return undefined;
    const parsed = dayjs(value);
    return parsed.isValid() ? parsed.toDate() : undefined;
}

function longDate(date) {
    return date.toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" });
}

/**
 * Popover + Calendar replacement for antd RangePicker.
 *
 * `value` is `[start, end]` as "YYYY-MM-DD" strings, Date or dayjs objects.
 * `onChange` receives `["YYYY-MM-DD", "YYYY-MM-DD"]`, the shape the table
 * endpoints already expect in `date[]`. It fires once both ends are picked.
 */
export default function DateRangePicker({
    id,
    value,
    onChange,
    label = "Periode",
    placeholder = "Pilih periode",
    disabled = false,
    align = "start",
    className,
}) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(null);
    const twoMonths = useMediaQuery("(min-width: 640px)");

    const from = toDate(value?.[0]);
    const to = toDate(value?.[1]);
    const selected = draft ?? (from ? { from, to } : undefined);

    const handleOpenChange = (next) => {
        setOpen(next);
        if (!next) setDraft(null);
    };

    const handleSelect = (_range, day) => {
        if (!draft) {
            setDraft({ from: day, to: undefined });
            return;
        }
        const [start, end] = day < draft.from ? [day, draft.from] : [draft.from, day];
        onChange?.([dayjs(start).format(QUERY_FORMAT), dayjs(end).format(QUERY_FORMAT)]);
        handleOpenChange(false);
    };

    const text = from && to
        ? `${dayjs(from).format(QUERY_FORMAT)} → ${dayjs(to).format(QUERY_FORMAT)}`
        : placeholder;
    const accessibleName = from && to
        ? `${label}: ${longDate(from)} sampai ${longDate(to)}`
        : `${label}: ${placeholder}`;

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    aria-label={accessibleName}
                    className={cn(
                        "h-[38px] justify-start gap-2 border-input bg-card px-3 font-mono text-[13px] font-medium",
                        !(from && to) && "font-sans text-muted-foreground",
                        className
                    )}
                >
                    <CalendarOutlined aria-hidden="true" className="text-subtle-foreground" />
                    {text}
                </Button>
            </PopoverTrigger>
            <PopoverContent align={align} className="w-auto p-0">
                <Calendar
                    mode="range"
                    locale={idLocale}
                    numberOfMonths={twoMonths ? 2 : 1}
                    defaultMonth={selected?.from}
                    selected={selected}
                    onSelect={handleSelect}
                    autoFocus
                />
                <p className="border-t px-3 py-2 text-xs text-muted-foreground">
                    {draft ? "Pilih tanggal akhir." : "Pilih tanggal awal, lalu tanggal akhir."}
                </p>
            </PopoverContent>
        </Popover>
    );
}
