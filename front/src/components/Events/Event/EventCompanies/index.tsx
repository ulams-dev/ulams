import { useContext } from "react";
import { useTranslation } from "react-i18next";
import ResponsiveImage from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { UlamsContext } from "@ulams/sdk/react";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { EventCompaniesStyles } from "./EventCompaniesStyles";

const EventCompanies = () => {
  const { settings } = useContext(UlamsContext);
  const { t } = useTranslation();

  return (
    <EventCompaniesStyles>
      <section className="event-companies">
        <Text>
          <strong>{t("CoursePage.CompaniesTitle")}</strong>
        </Text>
        <div className="companies-row">
          {settings &&
            settings.value.courseLogos &&
            Object.values(settings.value.courseLogos).map((_, index) => (
              <div className="single-company" key={index}>
                <ResponsiveImage
                  path={settings?.value?.courseLogos[`logo${index + 1}`] || ""}
                  srcSizes={[100, 200, 300]}
                />
              </div>
            ))}
        </div>
      </section>
    </EventCompaniesStyles>
  );
};

export default EventCompanies;
