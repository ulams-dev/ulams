import { useContext } from "react";
import { EscolaLMSContext } from "@lms/sdk/react/context";
import { fixContentForMarkdown } from "@lms/components/utils/components/markdown";
import { Title } from "@lms/components/components/atoms/Typography/Title";
import MarkdownRenderer from "@lms/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { useTranslation } from "react-i18next";

const EventDescription = () => {
  const { stationaryEvent } = useContext(EscolaLMSContext);
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
