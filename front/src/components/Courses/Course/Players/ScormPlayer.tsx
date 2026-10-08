import React, {
  ReactElement,
  FunctionComponent,
  useContext,
  useState,
} from "react";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { UlamsContext } from "@ulams/sdk/react";
import { useTranslation } from "react-i18next";
import { ResizeIcon } from "../../../../icons";
import { ScormPreview } from "@ulams/scorm-player";
import styles from "./ScormPlayer.module.css";

interface ScormPlayerProps {
  title: string;
  uuid: string;
}

const ScormPlayer: FunctionComponent<{
  value: ScormPlayerProps;
}> = ({ value }): ReactElement => {
  const { apiUrl } = useContext(UlamsContext);
  const { t } = useTranslation();
  const [fullView, setFullView] = useState(false);

  return (
    <div className="scorm-wrapper">
      <div className={`${styles.root} ${fullView ? styles.fullView : ""}`}>
        <Button onClick={() => setFullView(!fullView)}>
          {" "}
          {t("Scorm.Resize")} <ResizeIcon />
        </Button>
        <ScormPreview
          uuid={value.uuid}
          apiUrl={apiUrl}
          serviceWorkerUrl="/service-worker-scorm.js"
        />
        {/* <iframe
          title={value.title}
          src={`${apiUrl}/api/scorm/play/${value.uuid}`}
        /> */}
      </div>
    </div>
  );
};

export default ScormPlayer;
