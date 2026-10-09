import * as React from "react";
import "../styles/components.css";

export default function VisuallyHidden({ children }: { children?: React.ReactNode }) {
  return <span className="ulams-md-visually-hidden">{children}</span>;
}
