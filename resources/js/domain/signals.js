/**
 * Roadmap R4.2 (deck slide 26): how far a signal is from becoming a trend. Mirrors Signal::HORIZONS.
 * Pure data, no React.
 */
export const SIGNAL_HORIZONS = {
    likely: { label: "Likely", hint: "Tren saat ini, kemungkinan terjadi", tone: "approved" },
    could: { label: "Could", hint: "Pengetahuan saat ini, bisa terjadi", tone: "pending" },
    might: { label: "Might · weak signal", hint: "Pengetahuan masa depan, mungkin terjadi", tone: "neutral" },
};

/** A low-probability, high-impact event that could reshape the scenarios. */
export const WILD_CARD = { label: "Wild card", hint: "Peluang kecil, dampak besar" };

/**
 * True when the class came from the AI at extraction and no reviewer has saved it yet. Rows of
 * the signal list carry `classified_by`; rows of step 9 carry `proposed`.
 */
export function isProposedClass(signal) {
    if (!signal.horizon && !signal.wild_card) return false;
    return signal.proposed ?? signal.classified_by == null;
}

/** Values of the `sensing` filter of the signal list. */
export const SENSING_FILTERS = [
    ...Object.entries(SIGNAL_HORIZONS).map(([value, horizon]) => ({ value, label: horizon.label })),
    { value: "wild_card", label: WILD_CARD.label },
    { value: "none", label: "Belum diklasifikasi" },
];
