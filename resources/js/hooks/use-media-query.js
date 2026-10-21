import { useEffect, useState } from "react";

function matches(query) {
    return typeof window !== "undefined" && window.matchMedia(query).matches;
}

/** Tracks a CSS media query. Reads it synchronously on mount to avoid a layout flash. */
export function useMediaQuery(query) {
    const [value, setValue] = useState(() => matches(query));

    useEffect(() => {
        const media = window.matchMedia(query);
        const update = () => setValue(media.matches);
        update();
        media.addEventListener("change", update);
        return () => media.removeEventListener("change", update);
    }, [query]);

    return value;
}
