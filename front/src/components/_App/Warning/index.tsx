import React, { useContext, useState } from "react";

import styles from "./Warning.module.css";
import { UlamsContext } from "@ulams/sdk/react";
import { useTranslation } from "react-i18next";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { Note } from "@ulams/components/components/atoms/Note/Note";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { metaDataKeys } from "@/utils/meta";

const Warning = () => {
  const { settings } = useContext(UlamsContext);
  const [showWarning, setShowWarning] = useState(true);

  const { t } = useTranslation();

  const footerFromApi: string =
    settings?.value?.config?.[metaDataKeys.footerWarningMetaKey];

  const handleClick = () => {
    localStorage.setItem("hideWarning", "true");
    setShowWarning(false);
  };

  if (!showWarning || !footerFromApi) {
    return <React.Fragment />;
  }

  return (
    <aside className={styles.aside}>
      <Note
        time={
          <Button mode="outline" onClick={handleClick}>
            {t("I'm aware")}
          </Button>
        }
        description={<MarkdownRenderer>{footerFromApi}</MarkdownRenderer>}
      />
    </aside>
  );
};

export default Warning;
