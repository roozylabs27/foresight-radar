import { useCallback, useState } from "react";

/**
 * Form values plus the values the form was opened with, so a dialog can tell whether the user
 * changed anything (PRD-19: ask before discarding typed text). `reset(start)` sets both.
 */
export function useDraft(empty) {
    const [values, setValues] = useState(empty);
    const [initial, setInitial] = useState(empty);
    const reset = useCallback((start) => {
        setValues(start);
        setInitial(start);
    }, []);

    return { values, setValues, reset, dirty: JSON.stringify(values) !== JSON.stringify(initial) };
}
