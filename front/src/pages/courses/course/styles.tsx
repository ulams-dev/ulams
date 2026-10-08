import React from "react";

import styles from "./styles.module.css";

export { styles };

/** Course page wrapper; also used by the consultation page. */
export const StyledCoursePage = ({
  className,
  ...rest
}: React.HTMLAttributes<HTMLDivElement>) => (
  <div
    className={[styles.coursePage, className].filter(Boolean).join(" ")}
    {...rest}
  />
);

/**
 * Former createGlobalStyle: while mounted, react-modal overlays sit above the
 * navbar (z-index 1500). Rendered inside the open modal only.
 */
export const ModalOverwriteGlobal = () => (
  <span className="ulams-course-modal-overlay-fix" hidden />
);
