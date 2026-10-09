import React, { ReactNode, useState } from "react";
import { Slider as SliderLMS } from "@ulams/components/components/atoms/Slider/Slider";
import { Settings } from "react-slick";
import styles from "./Slider.module.css";

const defaultSliderSettings = {
  arrows: false,
  infinite: true,
  speed: 500,
  draggable: false,
  slidesToShow: 4,
  slidesToScroll: 4,
  responsive: [
    {
      breakpoint: 1201,
      settings: {
        slidesToShow: 3,
        slidesToScroll: 3,
      },
    },
    {
      breakpoint: 768,
      settings: {
        draggable: true,
        slidesToShow: 2,
        slidesToScroll: 2,
      },
    },
    {
      breakpoint: 576,
      settings: {
        slidesToShow: 1,
        centerMode: true,
        slidesToScroll: 1,
      },
    },
  ],
};

type Props = {
  nodes: ReactNode | ReactNode[];
  sliderSettings?: Settings;
};

const Slider: React.FC<Props> = ({
  nodes,
  sliderSettings = defaultSliderSettings,
}) => {
  const [dots] = useState(true);

  return (
    <div className={styles.content}>
      <div>
        <SliderLMS
          settings={{
            ...sliderSettings,
            dots,
            onSwipe: () => {
              const allHiddenSlides = document.querySelectorAll(
                '.slick-slide[aria-hidden="true"]'
              );
              const allVisibleSlides = document.querySelectorAll(
                '.slick-slide[aria-hidden="false"]'
              );
              allVisibleSlides.forEach((visibleSlide) =>
                visibleSlide.removeAttribute("aria-modal")
              );
              allHiddenSlides.forEach((hiddenSlide) =>
                hiddenSlide.setAttribute("aria-modal", "true")
              );
            },
            onInit: () => {
              const allHiddenSlides = document.querySelectorAll(
                '.slick-slide[aria-hidden="true"]'
              );
              allHiddenSlides.forEach((hiddenSlide) =>
                hiddenSlide.setAttribute("aria-modal", "true")
              );
            },
          }}
          dotsPosition="top right"
        >
          {nodes}
        </SliderLMS>
      </div>
    </div>
  );
};

export default Slider;
