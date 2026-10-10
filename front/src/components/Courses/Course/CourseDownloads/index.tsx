import React from "react";
import { Downloads } from "@ulams/components/components/molecules/Downloads/Downloads";
import { useTranslation } from "react-i18next";
import { API } from "@ulams/sdk";
import styles from "./styles.module.css";

type Props = {
  resources: API.TopicResource[];
  subtitle: string;
};

const CourseDownloads: React.FC<Props> = ({ resources, subtitle }) => {
  const { t } = useTranslation();
  const mappedResources = resources.map((item) => {
    return { href: item.url, fileName: item.name };
  });
  return (
    <div className={styles.root}>
      <Downloads
        subtitle={subtitle}
        title={t("CourseProgram.TopicAttachment")}
        downloads={mappedResources}
      />
    </div>
  );
};

export default CourseDownloads;
