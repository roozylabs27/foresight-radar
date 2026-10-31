import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/ui/tooltip";

/**
 * Hover/focus hint built on the shadcn tooltip, used instead of the browser's
 * native `title` tooltip (slow, unstyled, not themed). The child must accept a
 * ref and pointer/focus handlers: a DOM element, including SVG elements. With
 * no `content` the child renders unchanged. Carries its own provider so it
 * also works outside AuthenticatedLayout.
 */
export default function HintTooltip({ content, side = "top", children }) {
    if (content === null || content === undefined || content === "") return children;

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>{children}</TooltipTrigger>
                <TooltipContent side={side} sideOffset={4} className="max-w-xs">
                    {content}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
