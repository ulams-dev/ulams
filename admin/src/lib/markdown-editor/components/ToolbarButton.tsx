import * as React from "react";
import "../styles/components.css";
import { cx } from "../themeContext";

type Props = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  active?: boolean;
};

export default function ToolbarButton({ active, className, ...rest }: Props) {
  return (
    <button
      className={cx(
        "ulams-md-toolbar-button",
        active && "ulams-md-toolbar-button--active",
        className,
      )}
      {...rest}
    />
  );
}
