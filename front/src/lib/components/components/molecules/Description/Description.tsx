import * as React from "react";

import styles from "./Description.module.css";

import { Title } from "../../atoms/Typography/Title";
import { Text } from "../../atoms/Typography/Text";
import { PropsWithChildren } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

export interface DescriptionProps
  extends React.HTMLProps<HTMLDivElement>,
    ExtendableStyledComponent {}

export const Description: React.FC<PropsWithChildren<DescriptionProps>> = (
  props
) => {
  const { children, title, className = "" } = props;

  return (
    <div className={`ulams-component ${styles.root} ${className}`}>
      <Text
        style={{
          textTransform: "uppercase",
          marginBottom: "8px",
          fontSize: "12px",
        }}
      >
        {title}
      </Text>
      <Title
        level={5}
        style={{
          marginBottom: 0,
          color: "var(--ulams-color-primary)",
        }}
        as={"h1"}
      >
        {children}
      </Title>
    </div>
  );
};

export default Description;
