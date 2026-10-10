import { useContext } from "react";
import { Row, Col } from "react-grid-system";
import { useTranslation } from "react-i18next";
import { isMobile } from "react-device-detect";
import Title from "@ulams/components/components/atoms/Typography/Title";
import ResponsiveImage from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import LabelListItem from "@ulams/components/components/molecules/LabelListItem/LabelListItem";
import { formatDate } from "@/utils/date";
import { UlamsContext } from "@ulams/sdk/react";
import styles from "./EventInfo.module.css";
import { Medal, StarOrange, ThumbUp } from "../../../../icons";

const EventInfo = () => {
  const { stationaryEvent } = useContext(UlamsContext);
  const { t } = useTranslation();

  if (!stationaryEvent.value) {
    return null;
  }
  return (
    <div className={styles.root}>
      <section className="event-main-info with-border">
        <Row>
          <Col lg={7}>
            <Title mobile={isMobile} level={2}>
              {stationaryEvent.value.name}
            </Title>
            <div className="labels-row">
              <div className="single-label">
                <LabelListItem mobile={isMobile} title="90%" icon={<ThumbUp />}>
                  {t("CoursePage.Recommends")}
                </LabelListItem>
              </div>
              <div className="single-label">
                <LabelListItem
                  mobile={isMobile}
                  title={t("CoursePage.Guarantee")}
                  icon={<Medal />}
                >
                  {t("CoursePage.Satisfaction")}
                </LabelListItem>
              </div>
              <div className="single-label">
                <LabelListItem
                  mobile={isMobile}
                  title="5.0"
                  icon={<StarOrange />}
                >
                  {t("CoursePage.AvarageRating")}
                </LabelListItem>
              </div>
            </div>
          </Col>
          <Col lg={4}>
            {stationaryEvent.value.image_path && (
              <div className="image-wrapper">
                <ResponsiveImage
                  path={stationaryEvent.value.image_path}
                  srcSizes={[790 * 0.5, 790, 2 * 790]}
                />
              </div>
            )}
          </Col>
        </Row>
        <div className="labels-row labels-row--bottom">
          {stationaryEvent.value.categories &&
            stationaryEvent.value.categories.length > 0 && (
              <div className="single-label">
                <LabelListItem
                  title={t("CoursePage.CourseCategory")}
                  variant={"label"}
                >
                  {stationaryEvent.value.categories[0].name}
                </LabelListItem>
              </div>
            )}
          {stationaryEvent.value.started_at && (
            <div className="single-label">
              <LabelListItem
                title={t("CoursePage.StartDate")}
                variant={"label"}
              >
                {stationaryEvent.value.started_at
                  ? formatDate(stationaryEvent.value.started_at)
                  : "---"}
              </LabelListItem>
            </div>
          )}
        </div>
      </section>
    </div>
  );
};

export default EventInfo;
