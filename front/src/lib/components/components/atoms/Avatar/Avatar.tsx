import * as React from "react";

import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { AvatarTypesStr } from "../../../types/AvatarTypes";
import { setAvatarBySize } from "../../../utils/components/primitives/avatarUtils";
import { cx } from "../../../utils/cx";
import styles from "./Avatar.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface AvatarProps
  extends React.ImgHTMLAttributes<HTMLImageElement>,
    ExtendableStyledComponent {
  size?: AvatarTypesStr;
}

export const Avatar: React.FC<AvatarProps> = ({
  size,
  className = "",
  style,
  ...props
}) => {
  return (
    <img
      {...props}
      style={
        { "--avatar-size": setAvatarBySize(size), ...style } as React.CSSProperties
      }
      className={cx(styles.avatar, "ulams-component", className)}
    />
  );
};

export default legacyDefault(Avatar);
