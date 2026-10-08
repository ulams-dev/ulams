import { Spin } from "@ulams/components/components/atoms/Spin/Spin";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import styles from "./ContentLoader.module.css";

interface Props {
  width?: string;
  height?: string;
}

const ContentLoader = ({ width, height }: Props) => {
  // Spin paints SVG gradient stops, which need a raw colour value.
  const theme = useThemeTokens();
  return (
    <div
      className={styles.spinnerWrapper}
      style={{
        width: width || "100%",
        height: height || "100%",
      }}
    >
      <Spin color={theme?.primaryColor} />
    </div>
  );
};

export default ContentLoader;
