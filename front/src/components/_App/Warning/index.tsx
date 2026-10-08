import React, { useContext, useState } from "react";

import styled from "styled-components";
import { UlamsContext } from "@ulams/sdk/react";
import { useTranslation } from "react-i18next";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { Note } from "@ulams/components/components/atoms/Note/Note";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { metaDataKeys } from "@/utils/meta";

const StyledAside = styled.aside`
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  z-index: 1001;
  > div {
    padding-top: 5px;
    padding-bottom: 5px;
    align-items: center;
    margin-bottom: 0;
  }
  button {
    padding-top: 5px;
    padding-bottom: 5px;
  }
  .time {
    margin: 0;
  }
`;

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
    <StyledAside>
      <Note
        time={
          <Button mode="outline" onClick={handleClick}>
            {t("I'm aware")}
          </Button>
        }
        description={<MarkdownRenderer>{footerFromApi}</MarkdownRenderer>}
      />
    </StyledAside>
  );
};

export default Warning;
