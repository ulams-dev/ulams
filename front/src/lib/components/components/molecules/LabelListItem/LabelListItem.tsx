import * as React from "react";
import type { PropsWithChildren } from "react";

import { Title } from "../../atoms/Typography/Title";
import { Text } from "../../atoms/Typography/Text";
import { IconTitle } from "../../atoms/IconTitle/IconTitle";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import styles from "./LabelListItem.module.css";

export interface TitleProps
  extends Omit<React.HTMLProps<HTMLDivElement>, "title">,
    ExtendableStyledComponent {
  variant?: "header" | "label";
  icon?: React.ReactNode;
  mobile?: boolean;
  title?: React.ReactNode;
}

export const LabelListItem: React.FC<PropsWithChildren<TitleProps>> = (
  props
) => {
  const {
    children,
    variant = "header",
    title,
    icon,
    mobile = false,
    className = "",
  } = props;

  return (
    <div className={`ulams-component ${styles.root} ${className}`}>
      {variant === "header" ? (
        <React.Fragment>
          {title &&
            (React.isValidElement(title) ? (
              title
            ) : (
              <IconTitle
                level={mobile ? 5 : 4}
                title={typeof title === "string" ? title : ""}
                icon={icon}
                as={"h1"}
              />
            ))}
          <Text
            noMargin={true}
            size={mobile ? "12" : "14"}
            style={{
              marginLeft: mobile ? "24px" : "0",
            }}
          >
            {children}
          </Text>
        </React.Fragment>
      ) : (
        <React.Fragment>
          <Text
            style={{
              textTransform: "uppercase",
              marginBottom: "8px",
            }}
            size={"12"}
          >
            {title}
          </Text>
          <Title
            level={5}
            style={{
              marginBottom: "0",
              color: "var(--ulams-color-primary)",
            }}
            as={"h1"}
          >
            {children}
          </Title>
        </React.Fragment>
      )}
    </div>
  );
};

export default LabelListItem;
