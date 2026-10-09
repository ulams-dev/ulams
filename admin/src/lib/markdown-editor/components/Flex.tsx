import * as React from "react";
import "../styles/components.css";
import { cx } from "../themeContext";

type JustifyValues = "center" | "space-around" | "space-between" | "flex-start" | "flex-end";

type AlignValues = "stretch" | "center" | "baseline" | "flex-start" | "flex-end";

type Props = React.HTMLAttributes<HTMLDivElement> & {
  column?: boolean;
  align?: AlignValues;
  justify?: JustifyValues;
  auto?: boolean;
};

const Flex = React.forwardRef<HTMLDivElement, Props>(function Flex(
  { column, align, justify, auto, className, ...rest },
  ref,
) {
  return (
    <div
      ref={ref}
      className={cx(
        "ulams-md-flex",
        auto && "ulams-md-flex--auto",
        column && "ulams-md-flex--column",
        align && `ulams-md-flex--align-${align}`,
        justify && `ulams-md-flex--justify-${justify}`,
        className,
      )}
      {...rest}
    />
  );
});

export default Flex;
