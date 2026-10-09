import React, { useContext, useEffect, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import styles from "./ScormPlayer.module.css";

interface LtiPlayerProps {
  topicId: number;
  title: string;
}

type Launch =
  | { kind: "loading" }
  | { kind: "ready"; url: string; presentation: string; tool: string }
  | { kind: "error"; message: string };

/**
 * External tool (LTI 1.3). The API returns the tool's OIDC login URL with a short-lived,
 * single-use hint; the tool then completes the launch inside the iframe (no cookies needed).
 * Tools that refuse framing, or links set to open in a window, get a button instead.
 */
const LtiPlayer: React.FC<LtiPlayerProps> = ({ topicId, title }) => {
  const { apiUrl, token } = useContext(UlamsContext);
  const [launch, setLaunch] = useState<Launch>({ kind: "loading" });

  useEffect(() => {
    const controller = new AbortController();
    setLaunch({ kind: "loading" });
    fetch(`${apiUrl}/api/lti/launches/${topicId}`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      signal: controller.signal,
    })
      .then(async (response) => {
        const body = await response.json().catch(() => null);
        if (!response.ok || !body?.data?.url) {
          throw new Error(body?.message ?? "The activity could not be opened.");
        }
        setLaunch({ kind: "ready", ...body.data });
      })
      .catch((e: Error) => {
        if (!controller.signal.aborted) {
          setLaunch({ kind: "error", message: e.message });
        }
      });
    return () => controller.abort();
  }, [apiUrl, token, topicId]);

  if (launch.kind === "loading") {
    return <div className="scorm-loader" role="status" aria-live="polite" />;
  }
  if (launch.kind === "error") {
    return <p role="alert">{launch.message}</p>;
  }
  if (launch.presentation === "window") {
    return (
      <p>
        <a href={launch.url} target="_blank" rel="noopener noreferrer">
          Open {launch.tool} in a new window
        </a>
      </p>
    );
  }

  return (
    <div className={styles.root}>
      <iframe
        title={title}
        src={launch.url}
        allow="fullscreen; clipboard-write; microphone; camera"
        referrerPolicy="origin"
      />
    </div>
  );
};

export default LtiPlayer;
