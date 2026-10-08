import { useCallback, useEffect, useId, useState } from "react";

/** Disclosure state for the mobile navigation: Escape closes and returns focus. */
export function useMenu() {
  const [open, setOpen] = useState(false);
  const id = useId();
  const menuId = `landing-menu-${id.replace(/:/g, "")}`;
  const buttonId = `${menuId}-button`;

  const close = useCallback(() => setOpen(false), []);
  const toggle = useCallback(() => setOpen((v) => !v), []);

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        document.getElementById(buttonId)?.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open, buttonId]);

  return {
    open,
    close,
    buttonProps: {
      id: buttonId,
      type: "button" as const,
      "aria-expanded": open,
      "aria-controls": menuId,
      onClick: toggle,
    },
    menuProps: { id: menuId, "data-open": open ? "true" : "false" },
  };
}
