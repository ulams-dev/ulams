import { useContext } from "react";
import ResponsiveImage from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { UlamsContext } from "@ulams/sdk/react";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import styles from "./CompaniesSection.module.css";

interface CompaniesSectionProps {
  title: React.ReactElement | React.ReactElement[];
}

const CompaniesSection = ({ title }: CompaniesSectionProps) => {
  const { settings } = useContext(UlamsContext);

  return (
    <div className={styles.root}>
      <section className="section-companies with-border">
        <Text>
          <strong>{title}</strong>
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
    </div>
  );
};

export default CompaniesSection;
