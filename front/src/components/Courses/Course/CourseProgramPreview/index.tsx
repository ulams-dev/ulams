import React, { useContext, useMemo } from "react";
import { API } from "@ulams/sdk";
import { TopicType } from "@ulams/sdk/services/courses";
import { OEmbedPlayer } from "@ulams/components/components/players/OEmbedPlayer/OEmbedPlayer";
import { AudioVideoPlayer } from "@ulams/components/components/players/AudioVideoPlayer/AudioVideoPlayer";
import { PdfPlayer } from "@ulams/components/components/players/PdfPlayer/PdfPlayer";
import { MarkdownPlayer } from "@ulams/components/components/players/MarkdownPlayer/MarkdownPlayer";
import { ImagePlayer } from "@ulams/components/components/players/ImagePlayer/ImagePlayer";
import { UlamsContext } from "@ulams/sdk/react";
import { H5Player } from "@ulams/components/components/players/H5Player/H5Player";
import GiftQuizPlayer from "@ulams/components/components/quizzes";
import { ScormPreview } from "@ulams/scorm-player";

export const CourseProgramPreview: React.FC<{
  topic: API.Topic;
}> = ({ topic }) => {
  const { apiUrl } = useContext(UlamsContext);
  const topicRender = useMemo(() => {
    if (topic && topic.topicable_type) {
      switch (topic.topicable_type) {
        case TopicType.H5P:
          return (
            <H5Player
              contentId={topic.topicable.value}
              hideActionButtons
              readOnlyState
            />
          );
        case TopicType.OEmbed:
          return <OEmbedPlayer url={topic?.topicable?.value} />;
        case TopicType.RichText:
          return (
            <div className="container-xl">
              <MarkdownPlayer
                children={topic.topicable.value}
                onLoad={() => console.log("MarkdownPlayer onLoad")}
              />
            </div>
          );
        case TopicType.Video:
        case TopicType.Audio:
          return <AudioVideoPlayer url={topic.topicable.url} />;
        case TopicType.Image:
          return <ImagePlayer topic={topic} onLoad={() => console.log("")} />;
        case TopicType.Pdf:
          return (
            <PdfPlayer
              url={topic.topicable.url}
              pageConfig={{
                width: 550,
              }}
            />
          );
        case TopicType.Scorm:
          return (
            <div className="scorm-wrapper">
              <div
                style={{
                  height: "440px",
                }}
              >
                <ScormPreview
                  uuid={topic.topicable.uuid}
                  apiUrl={apiUrl}
                  serviceWorkerUrl="/service-worker-scorm.js"
                />
              </div>

              {/* <iframe
                title={topic.topicable.value}
                width="100%"
                height="400px"
                style={{
                  border: "none",
                }}
                src={`${apiUrl}/api/scorm/play/${topic.topicable.uuid}`}
              /> */}
            </div>
          );
        case API.TopicType.GiftQuiz:
          return <GiftQuizPlayer topic={topic} />;
        default:
          return <pre>{topic.topicable_type}</pre>;
      }
    }
    return <React.Fragment />;
  }, [topic, apiUrl]);

  return (
    <div className="topic-preview-modal">
      <div className="topic-preview-modal-content">{topicRender}</div>
    </div>
  );
};

export default CourseProgramPreview;
