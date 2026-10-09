import { ReactElement, FunctionComponent, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { API } from "@ulams/sdk";
import { Download } from "@ulams/components/components/atoms/Download/Download";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import styles from "./TextPlayer.module.css";

const TextPlayer: FunctionComponent<{
  value?: string;
  onLoad?: () => void;
  resources?: API.TopicResource[];
}> = ({ value, onLoad, resources }): ReactElement => {
  const { t } = useTranslation();
  const isResources = resources && resources.length > 0;

  useEffect(() => {
    value && onLoad && onLoad();
  }, [value, onLoad]);

  return (
    <div className={styles.root}>
      {value && <MarkdownRenderer>{value}</MarkdownRenderer>}
      {isResources && (
        <div className={styles.resourcesContainer}>
          <Text>{t("CoursePage.Resources")}</Text>
          {resources.map(({ name, url }) => (
            <Download href={url} fileName={name} />
          ))}
        </div>
      )}
    </div>
  );
};

export default TextPlayer;
