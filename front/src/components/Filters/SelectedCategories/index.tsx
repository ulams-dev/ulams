import React from "react";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { useTranslation } from "react-i18next";
import styles from "./SelectedCategories.module.css";

import { CloseIcon } from "@/icons/index";

type Props = {
  onClearCategories: () => void;
  prevCategories: { id: number; name: string }[];
  handleRemoveCategory: (id: number) => void;
};

const SelectedCategories: React.FC<Props> = ({
  onClearCategories,
  prevCategories,
  handleRemoveCategory,
}) => {
  const { t } = useTranslation();

  return (
    <div className={styles.root}>
      {prevCategories.map((category) => (
        <button className={styles.category} onClick={() => handleRemoveCategory(category.id)}>
          <Text size={"13"}>{category.name}</Text>
          <CloseIcon />
        </button>
      ))}
      <button className={styles.clearCategories} onClick={onClearCategories}>
        <Text size="13">{t("CoursesPage.clearAll")}</Text>
      </button>
    </div>
  );
};

export default SelectedCategories;
