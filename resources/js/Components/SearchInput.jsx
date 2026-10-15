import { useEffect, useState } from "react";
import { SearchOutlined } from "@ant-design/icons";

import { Input } from "@/Components/ui/input";
import { cn } from "@/lib/utils";

/**
 * Table search box: `onSearch` runs on Enter, and right away when the box is
 * cleared (like antd Input.Search). `value` is the applied search, so resetting
 * it from the page clears the box too.
 */
export default function SearchInput({ value = "", onSearch, placeholder, label, className }) {
    const [text, setText] = useState(value);

    useEffect(() => setText(value), [value]);

    const handleSubmit = (event) => {
        event.preventDefault();
        onSearch(text.trim());
    };

    const handleChange = (event) => {
        setText(event.target.value);
        if (event.target.value === "") onSearch("");
    };

    return (
        <form role="search" onSubmit={handleSubmit} className={cn("relative", className)}>
            <SearchOutlined
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                aria-label={label}
                placeholder={placeholder}
                value={text}
                onChange={handleChange}
                className="bg-card pl-9"
            />
        </form>
    );
}
