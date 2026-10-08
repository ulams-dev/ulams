import * as React from "react";

import styles from "./ImageBubble.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { RatioBox } from "../../../index";

interface ImageBubbleImgProps {
  src: string;
  alt: string;
}

export interface ImageBubbleProps extends ExtendableStyledComponent {
  ratio?: number;
  children: React.ReactNode;
  header?: React.ReactNode;
  image: ImageBubbleImgProps | React.ReactNode;
}

export const ImageBubble: React.FC<ImageBubbleProps> = ({ ...props }) => {
  const { children, image, ratio = 1, header, className = "" } = props;
  return (
    <div className={`ulams-component ${styles.root} ${className}`}>
      <RatioBox ratio={ratio}>
        {React.isValidElement(image) ? (
          <React.Fragment>{image}</React.Fragment>
        ) : (
          <img
            className={"banner-img"}
            src={(image as ImageBubbleImgProps).src}
            alt={(image as ImageBubbleImgProps).alt}
          />
        )}
      </RatioBox>
      <div className="children-list">
        <div className="children-list__header">{header || " "}</div>
        <div className="children-list__items">{children}</div>
      </div>
    </div>
  );
};

export default ImageBubble;
