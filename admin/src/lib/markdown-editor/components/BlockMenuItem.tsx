import * as React from "react";
import scrollIntoView from "smooth-scroll-into-view-if-needed";
import theme from "../theme";
import { cx, withEditorTheme } from "../themeContext";
import "../styles/components.css";

type Props = {
  selected: boolean;
  disabled?: boolean;
  onClick: () => void;
  theme: typeof theme;
  icon: typeof React.Component | React.FC<any>;
  title: string;
  name?: string;
  shortcut?: string;
  upgradeCallback?: () => void;
  memberOnly?: boolean;
};

function BlockMenuItem({
  selected,
  disabled,
  onClick,
  title,
  shortcut,
  upgradeCallback,
  memberOnly,
  icon,
}: Props) {
  const Icon = icon;

  const ref = React.useCallback(
    node => {
      if (selected && node) {
        scrollIntoView(node, {
          scrollMode: "if-needed",
          block: "center",
          boundary: parent => {
            // All the parent elements of your target are checked until they
            // reach the #block-menu-container. Prevents body and other parent
            // elements from being scrolled
            return parent.id !== "block-menu-container";
          },
        });
      }
    },
    [selected]
  );
  return (
    <button
      className={cx(
        "ulams-md-block-menu-item",
        selected && "ulams-md-block-menu-item--selected"
      )}
      onClick={
        disabled
          ? undefined
          : memberOnly && upgradeCallback
          ? upgradeCallback
          : onClick
      }
      ref={ref}
    >
      <Icon color={selected ? theme.black : undefined} />
      &nbsp;&nbsp;{title}
      <span className="ulams-md-block-menu-item__shortcut">{shortcut}</span>
    </button>
  );
}

export default withEditorTheme(BlockMenuItem) as React.FC<Omit<Props, "theme">>;
