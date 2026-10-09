import React, {
  ReactElement,
  FunctionComponent,
  useContext,
  useEffect,
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

type Launch =
  | { kind: "loading" }
  | { kind: "content-origin"; url: string }
  | { kind: "legacy" };

/**
 * Asks the API for a player on the tenant content origin (api/docs/content-origin.md). The
 * package's JavaScript then runs on that origin with a SCO-scoped tracking token instead of on
 * this origin next to the learner's session. Without a content origin (or on any error) the
 * legacy in-page player is used, as before.
 */
async function launchOnContentOrigin(
  apiUrl: string,
  token: string,
  uuid: string,
  signal: AbortSignal
): Promise<string | null> {
  const response = await fetch(
    `${apiUrl}/api/scorm/launch/${encodeURIComponent(uuid)}`,
    {
      method: "POST",
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
      signal,
    }
  );
  if (!response.ok) {
    return null;
  }
  const body = await response.json();
  return typeof body?.data?.url === "string" ? body.data.url : null;
}

const ScormPlayer: FunctionComponent<{
  value: ScormPlayerProps;
}> = ({ value }): ReactElement => {
  const { apiUrl, token } = useContext(UlamsContext);
  const { t } = useTranslation();
  const [fullView, setFullView] = useState(false);
  const [launch, setLaunch] = useState<Launch>({ kind: "loading" });

  useEffect(() => {
    if (!token) {
      setLaunch({ kind: "legacy" });
      return;
    }
    const controller = new AbortController();
    setLaunch({ kind: "loading" });
    launchOnContentOrigin(apiUrl, token, value.uuid, controller.signal)
      .then((url) =>
        setLaunch(url ? { kind: "content-origin", url } : { kind: "legacy" })
      )
      .catch(() => {
        if (!controller.signal.aborted) {
          setLaunch({ kind: "legacy" });
        }
      });
    return () => controller.abort();
  }, [apiUrl, token, value.uuid]);

  return (
    <div className="scorm-wrapper">
      <div className={`${styles.root} ${fullView ? styles.fullView : ""}`}>
        <Button onClick={() => setFullView(!fullView)}>
          {" "}
          {t("Scorm.Resize")} <ResizeIcon />
        </Button>
        {launch.kind === "loading" && (
          <div className="scorm-loader" role="status" aria-live="polite" />
        )}
        {launch.kind === "content-origin" && (
          <iframe
            title={value.title}
            src={launch.url}
            // allow-same-origin is required: the SCO finds window.API in the player page (SANDBOX_SCORM)
            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-downloads"
            allow="fullscreen; autoplay"
            referrerPolicy="no-referrer"
          />
        )}
        {launch.kind === "legacy" && (
          <ScormPreview
            uuid={value.uuid}
            apiUrl={apiUrl}
            serviceWorkerUrl="/service-worker-scorm.js"
          />
        )}
      </div>
    </div>
  );
};

export default ScormPlayer;
