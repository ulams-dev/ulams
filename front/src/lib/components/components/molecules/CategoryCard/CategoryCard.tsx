import * as React from "react";
import { ReactNode } from "react";
import { Title } from "../../atoms/Typography/Title";
import { Button } from "../../atoms/Button/Button";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import styles from "./CategoryCard.module.css";

interface StyledCategoryCardProps {
  mobile?: boolean;
  variant: "solid" | "gradient";
}

export interface CategoryCardProps
  extends StyledCategoryCardProps,
    ExtendableStyledComponent {
  icon: ReactNode;
  title: ReactNode;
  subtitle: ReactNode;
  buttonText: string;
  onButtonClick: () => void;
}

export const CategoryCard: React.FC<CategoryCardProps> = (props) => {
  const {
    icon,
    title,
    subtitle,
    onButtonClick,
    variant,
    mobile = false,
    className = "",
  } = props;
  return (
    <div
      className={[
        "ulams-component",
        styles.root,
        variant === "solid" ? styles.solid : "",
        mobile ? styles.mobile : "",
        className,
      ]
        .filter(Boolean)
        .join(" ")}
    >
      <div className={"category-card-icon"}>{icon}</div>
      <div className="category-content">
        <Title as={"h4"} level={2} className={"category-card-title"}>
          {title}
        </Title>
        <Button
          mode={"secondary"}
          onClick={onButtonClick}
          style={{
            marginTop: "6px",
          }}
        >
          {subtitle}
        </Button>
      </div>
    </div>
  );
};

export default CategoryCard;
