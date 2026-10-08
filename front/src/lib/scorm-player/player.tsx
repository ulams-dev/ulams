import React, { useEffect, useState } from "react";
import { Scorm2004API, Scorm12API, AICC } from "scorm-again";
import "./style.css";

export interface ScormSettings {
  data: {
    entry_url_zip: string;
    entry_url: string;
  };
}

interface ScormPreviewProps {
  uuid: string; // SCORM package identifier
  apiUrl: string; // e.g. "https://your-backend.com"
  serviceWorkerUrl?: string; // default: "/service-worker-scorm.js"
  comunitate?: boolean;
  onScormPost?: (data: any) => Promise<any>;
  onScormGet?: (key: string) => Promise<any>;
}

declare global {
  interface Window {
    ScormSettings: ScormSettings | undefined;
    API: any;
    API_1484_11: any;
  }
}

const ScormPreview: React.FC<ScormPreviewProps> = ({
  uuid,
  apiUrl,
  serviceWorkerUrl = "/service-worker-scorm.js",
  comunitate,
  onScormPost,
  onScormGet,
}) => {
  const [loading, setLoading] = useState(true);
  const [iframeUrl, setIframeUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Register the Service Worker
  const registerServiceWorker =
    async (): Promise<ServiceWorkerRegistration | null> => {
      if (!("serviceWorker" in navigator)) {
        console.warn("Service Worker not supported in this browser.");
        return null;
      }
      try {
        const registration = await navigator.serviceWorker.register(
          serviceWorkerUrl,
          { scope: "/" }
        );
        if (registration.active) {
          return registration;
        }
        return await new Promise((resolve) => {
          (registration.installing || registration.waiting)?.addEventListener(
            "statechange",
            () => {
              resolve(registration);
            }
          );
        });
      } catch (err) {
        console.error("SW registration failed:", err);
        return null;
      }
    };

  // SCORM initialization
  const initializeScorm12 = (settings: ScormSettings["data"]) => {
    window.API = new Scorm12API(settings as any);

    if (comunitate && onScormPost && onScormGet) {
      window.API.on("LMSSetValue.cmi.*", (CMIElement: string, value: any) => {
        onScormPost({ cmi: { [CMIElement]: value } });
      });

      window.API.on("LMSGetValue.cmi.*", async (CMIElement: string) => {
        const value = await onScormGet(CMIElement);
        window.API.LMSSetValue(CMIElement, value);
      });

      window.API.on("LMSCommit", () => {
        onScormPost({ cmi: window.API.cmi });
      });
    }
  };
  const initializeScorm2004 = (settings: ScormSettings["data"]) => {
    window.API_1484_11 = new Scorm2004API(settings as any);
    if (comunitate && onScormPost && onScormGet) {
      window.API_1484_11.on(
        "SetValue.cmi.*",
        (CMIElement: string, value: any) => {
          onScormPost({ cmi: { [CMIElement]: value } });
        }
      );

      window.API_1484_11.on("GetValue.cmi.*", async (CMIElement: string) => {
        const value = await onScormGet(CMIElement);
        window.API_1484_11.SetValue(CMIElement, value);
      });

      window.API_1484_11.on("Commit", () => {
        onScormPost({ cmi: window.API_1484_11.cmi });
      });
    }
  };
  const initializeAICC = (settings: ScormSettings["data"]) => {
    window.API = new AICC(settings as any);
  };

  // Load SCORM
  const loadScormSCO = async (registration: ServiceWorkerRegistration) => {
    try {
      // 1) Fetch SCORM settings from your backend
      const res = await fetch(`${apiUrl}/api/scorm/show/${uuid}`);
      if (!res.ok) throw new Error("Failed to fetch SCORM settings");
      const { data } = (await res.json()) as ScormSettings;

      // 2) Check if ZIP is there (just HEAD request)
      const zipHead = await fetch(data.entry_url_zip, { method: "HEAD" });
      if (!zipHead.ok) {
        // attempt to create zip
        await fetch(`${apiUrl}/api/scorm/zip/${uuid}`);
      }

      // 3) Ask the SW to load the ZIP
      registration.active?.postMessage(data.entry_url_zip);

      // 4) Wait for SW "loaded zip" message
      navigator.serviceWorker.addEventListener(
        "message",
        (event: MessageEvent) => {
          const { scormObj } = event.data;
          if (!scormObj) return;
          console.log("SW scormObj:", scormObj);

          // pick SCORM version
          switch (scormObj.version) {
            case "2004":
              initializeScorm2004(data);
              break;
            case "AICC":
              initializeAICC(data);
              break;
            default:
              initializeScorm12(data);
          }

          // Build final iframe URL
          const finalUrl = `${scormObj.PREFIX}/${data.entry_url}`;
          setIframeUrl(finalUrl);
          setLoading(false);
        },
        { once: true }
      ); // Listen once
    } catch (err) {
      console.error(err);
      setError("Failed to load SCORM SCO");
      setLoading(false);
    }
  };

  useEffect(() => {
    (async () => {
      setLoading(true);
      setError(null);
      setIframeUrl(null);

      const reg = await registerServiceWorker();
      if (!reg) {
        setError("Service Worker registration failed.");
        setLoading(false);
        return;
      }
      await loadScormSCO(reg);
    })();
  }, [uuid]);

  // Render
  if (loading) return <div className="scorm-loader"></div>;
  if (error) return <div className="scorm-serror">{error}</div>;
  if (iframeUrl) {
    return (
      <iframe className="scorm-iframe" src={iframeUrl} title="SCORM Content" />
    );
  }
  return null;
};

export default ScormPreview;
