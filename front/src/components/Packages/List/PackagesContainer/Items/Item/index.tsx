import { ReactNode } from "react";
import { useTranslation } from "react-i18next";
import { isMobile } from "react-device-detect";
import { Link, useHistory } from "react-router-dom";
import { CourseCard } from "@ulams/components/components/molecules/CourseCard/CourseCard";
import ResponsiveImage from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { API } from "@ulams/sdk";
import CourseImgPlaceholder from "@/components/Courses/CourseImgPlaceholder";
import Title from "@ulams/components/components/atoms/Typography/Title";
import Button from "@ulams/components/components/atoms/Button/Button";
import IconText from "@ulams/components/components/atoms/IconText/IconText";
import { UserIcon } from "../../../../../../icons";
import Tags from "@/components/Tags";
import CategoriesBreadCrumbs from "@/components/Categories/CategoriesBreadCrumbs";
import { Tag } from "@ulams/sdk/types";

interface Props {
  product: API.Product;
  actions?: ReactNode;
}

const PackagesContainerItem = ({ product, actions }: Props) => {
  const history = useHistory();
  const { t } = useTranslation();
  return (
    <CourseCard
      id={product.id}
      mobile={isMobile}
      image={
        <Link to={`/package/${product.id}`} aria-label={product.name}>
          {product.poster_path ? (
            <ResponsiveImage
              path={product.poster_path}
              alt={product.name}
              srcSizes={[300, 600, 900]}
            />
          ) : (
            <CourseImgPlaceholder />
          )}
        </Link>
      }
      title={
        <Link to={`/package/${product.id}`} className="title">
          <Title level={4} as="h2">
            {product.name}
          </Title>
        </Link>
      }
      categories={
        <CategoriesBreadCrumbs
          categories={
            product.categories as Ulams.Categories.Models.Category[]
          }
          onCategoryClick={(id) => {
            history.push(`/packages/?categories[]=${id}`);
          }}
        />
      }
      tags={
        <Tags
          tags={(product?.tags as Tag[]) || []}
          onTagClick={(tagName) => history.push(`/packages/?tag=${tagName}`)}
        />
      }
      actions={
        actions ?? (
          <>
            <Button
              mode="secondary"
              onClick={() => history.push(`/package/${product.id}`)}
            >
              {t("StartNow")}
            </Button>
          </>
        )
      }
      footer={
        <>
          {product.users_count && product.users_count > 0 ? (
            <IconText
              icon={<UserIcon />}
              text={`${product.users_count} ${t<string>("Students")}`}
            />
          ) : (
            ""
          )}
        </>
      }
    />
  );
};

export default PackagesContainerItem;
