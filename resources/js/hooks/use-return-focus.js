import { useCallback, useLayoutEffect, useRef } from "react";

const OVERLAY = '[role="menu"], [role="dialog"], [role="alertdialog"], [role="listbox"]';

// The last element focused outside menus and dialogs: the control that opened them.
let lastOpener = null;
if (typeof document !== "undefined") {
    document.addEventListener("focusin", (event) => {
        if (!event.target.closest?.(OVERLAY)) lastOpener = event.target;
    });
}

/**
 * `onCloseAutoFocus` handler for a dialog or sheet. Radix returns focus to the
 * element focused when it opened, which for a dialog opened from a dropdown
 * item is the removed item (so focus lands on <body>). This goes back to the
 * control that opened the menu instead, e.g. the row's actions button.
 */
export function useReturnFocus(open) {
    const opener = useRef(null);

    useLayoutEffect(() => {
        if (open) opener.current = lastOpener;
    }, [open]);

    return useCallback((event) => {
        const target = opener.current;
        if (target?.isConnected && target !== document.body) {
            event.preventDefault();
            target.focus();
        }
    }, []);
}
