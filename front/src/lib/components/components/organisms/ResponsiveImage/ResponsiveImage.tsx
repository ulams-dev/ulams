import React, { forwardRef } from "react";

import Image from "@ulams/sdk/react/components/Image";

import styles from "./ResponsiveImage.module.css";

interface ImageProps
  extends Omit<React.ImgHTMLAttributes<HTMLImageElement>, "onError"> {
  path: string;
  size?: number;
  srcSizes?: number[];
}

export const ResponsiveImage = forwardRef<HTMLImageElement, ImageProps>(
  (props, ref) => {
    return (
      <div className={`ulams-component ${styles.root} ${props.className ?? ""}`}>
        <Image {...props} ref={ref} />
      </div>
    );
  }
);

export default ResponsiveImage;
