import * as React from "react";
import { ReactNode, ReactChild, useMemo, useCallback } from "react";
import { Badge } from "../../atoms/Badge/Badge";
import { Button } from "../../atoms/Button/Button";
import { Card } from "../../atoms/Card/Card";
import {
  ProgressBar,
  ProgressBarProps,
} from "../../atoms/ProgressBar/ProgressBar";
import { RatioBox } from "../../atoms/RatioBox/RatioBox";
import { Text } from "../../atoms/Typography/Text";
import { Title } from "../../atoms/Typography/Title";
import { Link } from "../../atoms/Link/Link";
import { useThemeTokens } from "../../../theme/applyTheme";
import styles from "./CourseCard.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

type ImageObject = {
  path?: string;
  url?: string;
  alt: string;
};
type Image = ImageObject | ReactChild;

interface Tag {
  id: number;
  title: string;
}

interface Category {
  id: number;
  name: string;
}

interface Categories {
  onCategoryClick: (id: number) => void;
  categoryElements: Category[];
}

interface StyledCourseCardProps {
  mobile?: boolean;
  hideImage?: boolean;
}

// type guard
function isCategories(
  categories: Categories | ReactChild
): categories is Categories {
  return !React.isValidElement(categories);
}

export interface CourseCardProps
  extends StyledCourseCardProps,
    ExtendableStyledComponent {
  id: number;
  image?: Image;
  title?: ReactNode;
  categories?: Categories | ReactChild;
  tags?: Tag[] | ReactChild;
  subtitle?: ReactNode;
  //TODO: add params if needed to onImageClick
  onImageClick?: () => void;
  onTagClick?: (title: string) => void;
  onButtonClick?: (cardId: number) => void;
  buttonText?: string;
  onSecondaryButtonClick?: () => void;
  secondaryButtonText?: string;
  progress?: ProgressBarProps;
  actions?: ReactNode;
  footer?: ReactNode;
}

export const CourseCard: React.FC<CourseCardProps> = (props) => {
  const {
    id,
    mobile,
    title,
    image,
    categories,
    tags = [],
    onImageClick,
    onTagClick,
    onButtonClick,
    onSecondaryButtonClick,
    secondaryButtonText,
    buttonText,
    progress,
    hideImage,
    actions,
    footer,
    className = "",
  } = props;

  const theme = useThemeTokens();

  const tagClick = useCallback(
    (e: React.MouseEvent<HTMLDivElement, MouseEvent>, title: string) => {
      if (onTagClick) {
        onTagClick(title);
      }
    },
    []
  );

  const imageSrc = useMemo(() => {
    if (image && ((image as ImageObject).path || (image as ImageObject).url)) {
      const { path, url } = image as ImageObject;
      return path || url;
    }
  }, [image]);

  const imageSectionProps: React.HTMLAttributes<HTMLDivElement> =
    useMemo(() => {
      if (onImageClick) {
        return {
          onClick: onImageClick,
          onKeyUp: onImageClick,
          tabIndex: 0,
        };
      }
      return {};
    }, [onImageClick]);

  const renderCourseSection = () => {
    return (
      <>
        <div className="categories">
          {React.isValidElement(categories) ? (
            <>{categories}</>
          ) : (
            categories &&
            isCategories(categories) && (
              <Text className="categories">
                {categories.categoryElements.map((category, index) => {
                  return (
                    <React.Fragment key={index}>
                      <span
                        className={`category ${styles.category}`}
                        key={category.id}
                        onClick={() => categories.onCategoryClick(category.id)}
                        onKeyDown={() =>
                          categories.onCategoryClick(category.id)
                        }
                        role="button"
                        tabIndex={0}
                      >
                        {category.name}
                      </span>
                      {categories.categoryElements.length !== index + 1 && (
                        <span> / </span>
                      )}
                    </React.Fragment>
                  );
                })}
              </Text>
            )
          )}
        </div>

        {React.isValidElement(title) ? (
          title
        ) : (
          <Title level={mobile ? 5 : 4} as="h1" className="title">
            {title}
          </Title>
        )}
        {footer && <footer className="footer">{footer}</footer>}
        <div className={"card-course-footer"}>
          {actions && (
            <div className={"course-card-buttons-group"}>{actions}</div>
          )}
          {progress ? (
            <ProgressBar {...progress} />
          ) : (
            <div className={"course-card-buttons-group"}>
              {onButtonClick && buttonText && (
                <div>
                  <Button mode="secondary" onClick={() => onButtonClick(id)}>
                    {buttonText}
                  </Button>
                </div>
              )}
              {onSecondaryButtonClick && secondaryButtonText && (
                <div>
                  <Link onClick={onSecondaryButtonClick}>
                    {secondaryButtonText}
                  </Link>
                </div>
              )}
            </div>
          )}
        </div>
      </>
    );
  };

  return (
    <div
      className={[
        "ulams-component",
        styles.root,
        mobile ? styles.mobile : "",
        hideImage ? styles.hideImage : "",
        theme?.dm__breadcrumbsColor ? styles.hasDarkBreadcrumbs : "",
        className,
      ].join(" ")}
    >
      {!hideImage && (
        <div className="image-section">
          <div className={styles.imgWrapper}>
            <RatioBox ratio={mobile ? 66 / 100 : 1}>
              {React.isValidElement(image) ? (
                image
              ) : (
                <img
                  {...imageSectionProps}
                  className="image"
                  src={imageSrc}
                  alt={image ? (image as ImageObject).alt : undefined}
                />
              )}
            </RatioBox>
          </div>
          {!hideImage && tags && (
            <div className="information-in-image">
              <div className="badges">
                {React.isValidElement(tags)
                  ? tags
                  : Array.isArray(tags) &&
                    tags.map((tag: Tag) => (
                      <Badge
                        className="tag"
                        key={tag.id}
                        onClick={(e) => tagClick(e, tag.title)}
                        color={theme?.gray2}
                      >
                        {tag.title}
                      </Badge>
                    ))}
              </div>
              {/* {props.subtitle && (
              <div className="card">
                <Card wings="small">
                  <div className={"card-subtitle"}>{props.subtitle}</div>
                </Card>
              </div>
            )} */}
            </div>
          )}
        </div>
      )}

      {hideImage ? (
        <Card wings="large">{renderCourseSection()}</Card>
      ) : (
        <div className="course-section">{renderCourseSection()}</div>
      )}
    </div>
  );
};
