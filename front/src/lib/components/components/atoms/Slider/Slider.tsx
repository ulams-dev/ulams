import * as React from "react";
import SlickSlider, { Settings } from "react-slick";
import { PropsWithChildren } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Slider.module.css";
import { legacyDefault } from "../../../utils/legacy";

const DOTS_CLASS: Record<NonNullable<SliderProps["dotsPosition"]>, string> = {
  bottom: "",
  "bottom left": styles.dotsBottomLeft,
  "bottom right": styles.dotsBottomRight,
  top: styles.dotsTopCenter,
  "top left": styles.dotsTopLeft,
  "top right": styles.dotsTopRight,
};

interface StyledSliderProps {
  mobile?: boolean;
  borderRadius?: React.CSSProperties["borderRadius"];
}

export interface SliderProps
  extends StyledSliderProps,
    ExtendableStyledComponent {
  settings: Settings;
  dotsPosition?:
    | "top"
    | "top right"
    | "top left"
    | "bottom"
    | "bottom left"
    | "bottom right";
  children: React.ReactNode;
}

export const Slider: React.FC<PropsWithChildren<SliderProps>> = (props) => {
  const { children, settings, dotsPosition, mobile, borderRadius } = props;
  const isTop = Boolean(dotsPosition?.startsWith("top"));

  return (
    <div
      style={
        borderRadius !== undefined && borderRadius !== "" && borderRadius !== 0
          ? ({ "--slider-dot-radius": typeof borderRadius === "number" ? `${borderRadius}px` : borderRadius } as React.CSSProperties)
          : undefined
      }
      className={cx(
        styles.slider,
        dotsPosition && DOTS_CLASS[dotsPosition],
        settings.dots && (isTop ? styles.dotsTop : styles.dotsBottom),
        mobile && styles.mobile,
        "ulams-component",
        props.className ?? ""
      )}
    >
      <SlickSlider {...settings}>{children}</SlickSlider>
    </div>
  );
};

export default legacyDefault(Slider);
