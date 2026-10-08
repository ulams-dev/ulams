import { useEffect } from "react";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import styles from "./Preloader.module.css";

const Preloader = () => {
  // Spin paints SVG gradient stops, which need a raw colour value.
  const theme = useThemeTokens();
  useEffect(() => {
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = "inherit";
    };
  }, []);

  return (
    <div className={styles.loader}>
      <Spin color={theme?.primaryColor} />
    </div>
  );
};

export default Preloader;
