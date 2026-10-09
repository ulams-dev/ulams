import * as React from "react";
import { Property } from "csstype";

import { cx } from "../../../utils/cx";
import styles from "./Row.module.css";

export interface FlexBoxProps extends React.HTMLAttributes<HTMLDivElement> {
  $justifyContent?: Property.JustifyContent;
  $alignItems?: Property.AlignItems;
  $gap?: number;
  as?: React.ElementType;
}

/** Flex container shared by Row and Stack (former styled.div with `$` props). */
export const flexBox = (direction: "row" | "column", displayName: string) => {
  const Component = React.forwardRef<HTMLDivElement, FlexBoxProps>(
    (
      { $justifyContent, $alignItems, $gap, as, style, className, ...props },
      ref
    ) => {
      const Tag: React.ElementType = as ?? "div";
      return (
        <Tag
          ref={ref}
          {...props}
          className={cx(
            direction === "column" ? styles.stack : styles.row,
            className
          )}
          style={
            {
              // always set, so a nested Row/Stack never inherits its parent's values
              "--flex-justify": $justifyContent ?? "normal",
              "--flex-align": $alignItems ?? "normal",
              "--flex-gap": `${$gap ?? 0}px`,
              ...style,
            } as React.CSSProperties
          }
        />
      );
    }
  );
  Component.displayName = displayName;
  return Component;
};

export const Row = flexBox("row", "Row");
