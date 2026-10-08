import React, { useRef } from "react";
import { Swiper } from "swiper/react";
import { Navigation, A11y } from "swiper/modules";
import { Swiper as SwiperType } from "swiper/types";

import "swiper/css/bundle";
import "swiper/css/navigation";
import { ArrowRight } from "@/icons/index";
import styles from "./swiper.module.css";

type Props = {
  children?: React.ReactNode;
  slidesPerView?: number;
};

const SwiperSlider: React.FC<Props> = ({ children, slidesPerView }) => {
  const swiperRef = useRef<SwiperType>();
  return (
    <div>
      <Swiper
        modules={[Navigation, A11y]}
        spaceBetween={18}
        slidesOffsetAfter={18}
        breakpoints={{
          0: {
            slidesPerView: 1.3,
          },
          576: {
            slidesPerView: 2,
          },
          768: {
            slidesPerView: 3,
          },
          1201: {
            slidesPerView: slidesPerView,
          },
        }}
        onBeforeInit={(swiper) => {
          swiperRef.current = swiper;
        }}
      >
        {children}
      </Swiper>
      <div className={styles.swiperButtons}>
        <button onClick={() => swiperRef.current?.slidePrev()} title="pev">
          <ArrowRight />
        </button>
        <button onClick={() => swiperRef.current?.slideNext()} title="next">
          <ArrowRight />
        </button>
      </div>
    </div>
  );
};

export default SwiperSlider;
