import { ReactNode } from "react";
import { useTranslation } from "react-i18next";
import { isMobile } from "react-device-detect";
import { Link, useHistory } from "react-router-dom";
import { CourseCard } from "@lms/components/components/molecules/CourseCard/CourseCard";
import ResponsiveImage from "@lms/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { API } from "@lms/sdk";
import CourseImgPlaceholder from "@/components/Courses/CourseImgPlaceholder";
import Title from "@lms/components/components/atoms/Typography/Title";
import Button from "@lms/components/components/atoms/Button/Button";
import IconText from "@lms/components/components/atoms/IconText/IconText";
import { IconLocation, UserIcon } from "../../../../../../icons";
import Tags from "@/components/Tags";
import CategoriesBreadCrumbs from "@/components/Categories/CategoriesBreadCrumbs";
import { Tag } from "@lms/sdk/types";

interface Props {
  event: API.StationaryEvent;
  actions?: ReactNode;
}

const EventsContainerItem = ({ event, actions }: Props) => {
  const history = useHistory();
  const { t } = useTranslation();
  return (
    <CourseCard
      id={event.id}
      mobile={isMobile}
      image={
        <Link to={`/event/${event.id}`} aria-label={event.name}>
          {event.image_path ? (
            <ResponsiveImage
              path={event.image_path}
              alt={event.name}
              srcSizes={[300, 600, 900]}
            />
          ) : (
            <CourseImgPlaceholder />
          )}
        </Link>
      }
      title={
        <Link to={`/event/${event.id}`} className="title">
          <Title level={4} as="h2">
            {event.name}
          </Title>
        </Link>
      }
      categories={
        <CategoriesBreadCrumbs
          categories={
            event.categories as EscolaLms.Categories.Models.Category[]
          }
          onCategoryClick={(id) => {
            history.push(`/events/?categories[]=${id}`);
          }}
        />
      }
      tags={
        <Tags
          tags={(event.product?.tags as Tag[]) || []}
          onTagClick={(tagName) => history.push(`/events/?tag=${tagName}`)}
        />
      }
      actions={
        actions ?? (
          <>
            <Button
              mode="secondary"
              onClick={() => history.push(`/event/${event.id}`)}
            >
              {t("StartNow")}
            </Button>
          </>
        )
      }
      footer={
        <>
          {event.users_count && event.users_count > 0 ? (
            <IconText
              icon={<UserIcon />}
              text={`${event.users_count} ${t<string>("Students")}`}
            />
          ) : (
            ""
          )}{" "}
          {!!event.place && (
            <IconText icon={<IconLocation />} text={`${event.place}`} />
          )}
        </>
      }
    />
  );
};

export default EventsContainerItem;
