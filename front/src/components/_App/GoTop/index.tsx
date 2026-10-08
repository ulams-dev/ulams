import { ArrowUp } from "@/icons/index";
import React, { useEffect, useState } from "react";
import styles from "./GoTop.module.css";

const GoTop = () => {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    document.addEventListener("scroll", () => {
      if (window && window.scrollY > 70) {
        setIsVisible(true);
      } else {
        setIsVisible(false);
      }
    });

    return () => {
      window.removeEventListener("scroll", () => {});
    };
  }, []);

  const scrollToTop = () => {
    window.scroll({
      top: 0,
      left: 0,
      behavior: "smooth",
    });
  };

  const renderGoTopIcon = () => {
    return (
      <div
        className={`${styles.goTop} go-top ${isVisible ? "active" : ""}`}
        onClick={scrollToTop}
        onKeyDown={scrollToTop}
        role="button"
        tabIndex={-1}
      >
        <ArrowUp />
      </div>
    );
  };

  return <React.Fragment>{renderGoTopIcon()}</React.Fragment>;
};

export default GoTop;
