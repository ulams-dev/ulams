import * as React from "react";
import ReactDropdown, { ReactDropdownProps } from "react-dropdown";
import "react-dropdown/style.css";
import chroma from "chroma-js";
import { useThemeTokens } from "../../../theme/applyTheme";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import classes from "./Dropdown.module.css";

export interface DropdownProps
  extends ReactDropdownProps,
    ExtendableStyledComponent {
  placement?: "top" | "bottom";
  styles?: React.CSSProperties;
  lightContrast?: boolean;
  backgroundColor?: React.CSSProperties["backgroundColor"];
}

export const Dropdown: React.FC<DropdownProps> = (props) => {
  const theme = useThemeTokens();
  const {
    placement = "bottom",
    styles,
    className = "",
    backgroundColor,
  } = props;

  // Raw colour for the contrast check; CSS reads the variable.
  const themeBackground =
    theme?.mode === "dark" ? theme?.dm__background : theme?.background;
  const resolvedBackground = backgroundColor ?? themeBackground;

  const cts = React.useMemo(() => {
    if (!resolvedBackground) return false;
    try {
      return chroma.contrast("#fff", resolvedBackground) >= 1.85;
    } catch {
      return false;
    }
  }, [resolvedBackground]);

  return (
    <div
      style={
        {
          "--dropdown-bg": backgroundColor ?? "var(--ulams-color-bg)",
          ...styles,
        } as React.CSSProperties
      }
      className={[
        "ulams-component",
        classes.root,
        placement === "top" ? classes.top : classes.bottom,
        cts ? classes.lightContrast : "",
        className,
      ]
        .filter(Boolean)
        .join(" ")}
    >
      <ReactDropdown
        {...props}
        menuClassName="menu"
        controlClassName="control"
        arrowOpen={
          <svg
            className="arrows"
            width="14"
            height="8"
            viewBox="0 0 14 8"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M6.29289 0.292893C6.68342 -0.0976311 7.31658 -0.0976311 7.70711 0.292893L13.7071 6.29289C14.0976 6.68342 14.0976 7.31658 13.7071 7.70711C13.3166 8.09763 12.6834 8.09763 12.2929 7.70711L7 2.41421L1.70711 7.70711C1.31658 8.09763 0.683417 8.09763 0.292893 7.70711C-0.0976311 7.31658 -0.0976311 6.68342 0.292893 6.29289L6.29289 0.292893Z"
              fill="currentColor"
            />
          </svg>
        }
        arrowClosed={
          <svg
            className="arrows"
            width="14"
            height="8"
            viewBox="0 0 14 8"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M0.292893 0.292893C0.683417 -0.0976311 1.31658 -0.0976311 1.70711 0.292893L7 5.58579L12.2929 0.292893C12.6834 -0.0976311 13.3166 -0.0976311 13.7071 0.292893C14.0976 0.683418 14.0976 1.31658 13.7071 1.70711L7.70711 7.70711C7.31658 8.09763 6.68342 8.09763 6.29289 7.70711L0.292893 1.70711C-0.0976311 1.31658 -0.0976311 0.683418 0.292893 0.292893Z"
              fill="currentColor"
            />
          </svg>
        }
      />
    </div>
  );
};
