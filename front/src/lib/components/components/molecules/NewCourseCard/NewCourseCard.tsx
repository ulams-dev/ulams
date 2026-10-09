import * as React from "react";
import { ReactNode, ReactChild, useMemo } from "react";
import {
  ProgressBar,
  ProgressBarProps,
} from "../../atoms/ProgressBar/ProgressBar";
import { RatioBox } from "../../atoms/RatioBox/RatioBox";
import { useThemeTokens } from "../../../theme/applyTheme";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Text } from "../../../";
import { useTranslation } from "react-i18next";
import styles from "./NewCourseCard.module.css";

type ImageObject = {
  path?: string;
  url?: string;
  alt: string;
};
type Image = ImageObject | ReactChild;

interface Category {
  id: number;
  name: string;
}

export interface Categories {
  onCategoryClick: (id: number) => void;
  categoryElements: Category[];
}

interface StyledCourseCardProps {
  mobile?: boolean;
  hideImage?: boolean;
}

// type guard
function isCategories(
  categories: Categories | React.ReactChild | string
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
  onImageClick?: () => void;
  progress?: ProgressBarProps;
  price?: ReactNode;
  actions?: ReactNode;
  disabled?: boolean;
  footer?: ReactNode;
}

export const NewCourseCard: React.FC<CourseCardProps> = (props) => {
  const {
    mobile,
    title,
    image,
    categories,
    onImageClick,
    hideImage,
    className = "",
    price,
    progress,
    actions,
    disabled,
    footer,
  } = props;

  const { t } = useTranslation();
  // Dark-mode category colour follows dm__breadcrumbsColor only when the theme sets it
  // (otherwise gray3, or gray2 without an image), as getStylesBasedOnTheme did.
  const tokens = useThemeTokens();
  const hasDarkBreadcrumbs = !!tokens?.dm__breadcrumbsColor;

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

  return (
    <div
      className={[
        styles.root,
        mobile ? styles.mobile : "",
        hideImage ? styles.hideImage : "",
        hasDarkBreadcrumbs ? styles.darkBreadcrumbs : "",
        `ulams-component ${className} ${disabled ? "disabled" : ""}`,
      ].join(" ")}
    >
      <div>
        {!hideImage && (
          <div className="image-section">
            <RatioBox ratio={mobile ? 75 / 100 : 1}>
              {React.isValidElement(image) ? (
                image
              ) : (
                <div className="ulams-image">
                  <img
                    {...imageSectionProps}
                    className="image"
                    src={imageSrc}
                    alt={image ? (image as ImageObject).alt : undefined}
                  />
                </div>
              )}
            </RatioBox>
          </div>
        )}
        <div className="course-card__content">
          {React.isValidElement(categories) ? (
            <div className="categories">{categories}</div>
          ) : (
            categories &&
            isCategories(categories) && (
              <Text className="categories">
                {categories.categoryElements.map((category, index) => {
                  return (
                    <React.Fragment key={index}>
                      <span
                        className={`${styles.category} category`}
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
          <div className="course-title">{title}</div>{" "}
          <div className="course-price">{price}</div>
          <div className="course-actions">
            {actions && (
              <div className={"course-card-buttons-group"}>{actions}</div>
            )}
          </div>
          {footer && <footer className="footer">{footer}</footer>}
        </div>
      </div>
      {disabled && (
        <div className="lost-access">
          <Text>{t("LostAccess.Title")}</Text>

          <Text>{t("LostAccess.Description")}</Text>
        </div>
      )}

      {progress && (
        <div>
          <ProgressBar {...progress} />
        </div>
      )}
    </div>
  );
};
