import * as React from "react"
import { Switch as SwitchPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

const trackClass =
  "peer inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input"
const thumbClass =
  "pointer-events-none block size-4 rounded-full bg-background ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0 dark:data-[state=checked]:bg-primary-foreground dark:data-[state=unchecked]:bg-foreground"

function Switch({ className, ...props }) {
  return (
    <SwitchPrimitive.Root data-slot="switch" className={cn(trackClass, className)} {...props}>
      <SwitchPrimitive.Thumb data-slot="switch-thumb" className={thumbClass} />
    </SwitchPrimitive.Root>
  )
}

/**
 * Non-interactive look-alike for places that already own the toggle semantics,
 * such as a menuitemcheckbox in a dropdown (a real switch there would nest
 * interactive controls).
 */
function SwitchIndicator({ checked, className }) {
  const state = checked ? "checked" : "unchecked"
  return (
    <span aria-hidden="true" data-state={state} className={cn(trackClass, className)}>
      <span data-state={state} className={thumbClass} />
    </span>
  )
}

export { Switch, SwitchIndicator }
