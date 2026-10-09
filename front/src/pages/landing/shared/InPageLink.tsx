import React, { useCallback } from "react";

/**
 * Link to a section on the same page. The app may use HashRouter, where a
 * plain `href="#id"` would change the route, so the click scrolls to the
 * target and moves keyboard focus to it instead.
 */
export function focusSection(id: string): void {
  const target = document.getElementById(id);
  if (!target) return;
  const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  target.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
  if (!target.hasAttribute("tabindex")) target.setAttribute("tabindex", "-1");
  target.focus({ preventScroll: true });
}

type Props = Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, "href"> & {
  to: string;
};

export const InPageLink: React.FC<Props> = ({ to, onClick, children, ...rest }) => {
  const handleClick = useCallback(
    (event: React.MouseEvent<HTMLAnchorElement>) => {
      onClick?.(event);
      if (event.defaultPrevented) return;
      event.preventDefault();
      focusSection(to);
    },
    [onClick, to]
  );
  return (
    <a href={`#${to}`} onClick={handleClick} {...rest}>
      {children}
    </a>
  );
};

/** First focusable element on landing pages: jumps over the masthead. */
export const SkipLink: React.FC<{ className?: string; to?: string }> = ({
  className,
  to = "landing-main",
}) => (
  <InPageLink to={to} className={className}>
    Skip to content
  </InPageLink>
);
