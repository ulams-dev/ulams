import * as React from "react";
import "../styles/components.css";
import { cx } from "../themeContext";

type Props = React.InputHTMLAttributes<HTMLInputElement>;

export default function Input({ className, ...rest }: Props) {
  return <input className={cx("ulams-md-input", className)} {...rest} />;
}
