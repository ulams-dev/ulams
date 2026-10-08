import { useTranslation } from "react-i18next";
import { fixContentForMarkdown } from "@lms/components/utils/components/markdown";
import { Title } from "@lms/components/components/atoms/Typography/Title";
import MarkdownRenderer from "@lms/components/components/molecules/MarkdownRenderer/MarkdownRenderer";

interface DescriptionSectionProps {
  description?: string | null;
  title?: React.ReactElement | React.ReactElement[];
}

const DescriptionSection = ({
  description,
  title,
}: DescriptionSectionProps) => {
  const { t } = useTranslation();

  if (!description) {
    return null;
  }
  return (
    <>
      {description && fixContentForMarkdown(description) !== "" && (
        <section className="with-border">
          <Title level={4}>{title ?? t("SectionDescriptionTitle")}</Title>
          <MarkdownRenderer>{description}</MarkdownRenderer>
        </section>
      )}
    </>
  );
};

export default DescriptionSection;
