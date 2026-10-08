import { useCallback, useEffect, useRef } from "react";
import { JaaSMeeting } from "@jitsi/react-sdk";
import { IJitsiMeetExternalApi } from "@jitsi/react-sdk/lib/types";
import { API } from "@ulams/sdk";

type JitsyMeetingProps = {
  jitsyData: Omit<API.JitsyData, "yt_url" | "yt_stream_url" | "yt_stream_key">;
  close?: () => void;
  onRecordingAvailable?: (url: string) => void;
};

const JitsyMeeting: React.FC<JitsyMeetingProps> = ({
  jitsyData,
  close,
  onRecordingAvailable,
}) => {
  const apiRef = useRef<IJitsiMeetExternalApi | null>(null);

  const onApiReady = useCallback(
    (api: IJitsiMeetExternalApi) => {
      apiRef.current = api;

      api.on(
        "recordingLinkAvailable",
        (event: { link: string; ttl: number }) => {
          if (onRecordingAvailable) {
            onRecordingAvailable(event.link);
          }
        }
      );
    },
    [onRecordingAvailable]
  );

  useEffect(() => {
    return () => {
      if (apiRef.current) {
        apiRef.current.dispose();
        apiRef.current = null;
      }
    };
  }, []);

  const getProperRoomName = () => {
    const regex = /\/([^/?]+)\?/;
    const match = jitsyData.url.match(regex);
    return match ? match[1] : jitsyData.data.roomName;
  };

  return (
    <JaaSMeeting
      jwt={jitsyData.data.jwt}
      appId={jitsyData.data.app_id}
      roomName={getProperRoomName()}
      onApiReady={onApiReady}
      getIFrameRef={(iframeRef) => {
        iframeRef.style.height = "calc(100vh - 76px)";
        iframeRef.style.width = "100%";
      }}
      onReadyToClose={() => {
        close?.();
      }}
      interfaceConfigOverwrite={{
        ...jitsyData.data.interfaceConfigOverwrite,
      }}
      configOverwrite={{
        ...jitsyData.data.configOverwrite,
        prejoinConfig: {
          enabled: false,
        },
        constraints: {
          video: {
            deviceId: localStorage.getItem("preferredCamera") || undefined,
          },
          audio: {
            deviceId: localStorage.getItem("preferredMic") || undefined,
          },
        },
      }}
    />
  );
};

export default JitsyMeeting;
