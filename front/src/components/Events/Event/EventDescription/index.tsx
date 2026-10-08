import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import { fixContentForMarkdown } from "@ulams/components/utils/components/markdown";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import MarkdownRenderer from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { useTranslation } from "react-i18next";

const EventDescription = () => {
  const { stationaryEvent } = useContext(UlamsContext);
  const { t } = useTranslation();
  const description = stationaryEvent.value?.description;

  if (!description) {
    return null;
  }
  return (
    <>
      {description && fixContentForMarkdown(description) !== "" && (
        <section className="course-description-short with-border">
          <Title level={4}>{t("CoursePage.CourseDescriptionTitle")}</Title>
          <MarkdownRenderer>{description}</MarkdownRenderer>
        </section>
      )}
    </>
  );
};

export default EventDescription;
