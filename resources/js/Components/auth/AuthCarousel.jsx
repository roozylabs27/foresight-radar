import { useEffect, useState } from "react";

import { useMediaQuery } from "@/hooks/use-media-query";
import { cn } from "@/lib/utils";

/**
 * Rotating highlights for the auth brand panel. Advances every `interval` ms,
 * pauses while hovered or focused, and stays put with reduced motion; the dots
 * pick a slide directly.
 */
export default function AuthCarousel({ slides, interval = 5000, label = "Sorotan Foresight Radar" }) {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const reducedMotion = useMediaQuery("(prefers-reduced-motion: reduce)");
    const slide = slides[index];

    useEffect(() => {
        if (paused || reducedMotion || slides.length < 2) return undefined;
        const timer = window.setInterval(() => setIndex((current) => (current + 1) % slides.length), interval);
        return () => window.clearInterval(timer);
    }, [paused, reducedMotion, slides.length, interval]);

    return (
        <section
            aria-roledescription="carousel"
            aria-label={label}
            className="flex max-w-[480px] flex-col gap-6"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
        >
            <div
                role="group"
                aria-roledescription="slide"
                aria-label={`${index + 1} dari ${slides.length}`}
                // Announce changes only when the user moved the carousel themselves.
                aria-live={paused ? "polite" : "off"}
                className="min-h-[170px]"
            >
                <p className="m-0 text-[28px] leading-tight font-bold tracking-[-0.01em] text-on-night lg:text-[32px]">
                    {slide.title}
                </p>
                <p className="m-0 mt-4 text-base leading-relaxed text-on-night/80">{slide.description}</p>
            </div>

            <div className="flex items-center gap-1">
                {slides.map((item, slideIndex) => (
                    <button
                        key={item.title}
                        type="button"
                        onClick={() => setIndex(slideIndex)}
                        aria-label={`Tampilkan sorotan ${slideIndex + 1}`}
                        aria-current={slideIndex === index || undefined}
                        className="flex h-6 cursor-pointer items-center rounded-sm border-0 bg-transparent px-1 outline-none focus-visible:ring-[3px] focus-visible:ring-scan/60"
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                "block h-1 rounded-full transition-[width] motion-reduce:transition-none",
                                slideIndex === index ? "w-6 bg-on-night" : "w-2 bg-on-night/40"
                            )}
                        />
                    </button>
                ))}
            </div>
        </section>
    );
}
