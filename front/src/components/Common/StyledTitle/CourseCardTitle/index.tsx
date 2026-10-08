import { Title } from "@ulams/components/components/atoms/Typography/Title";
import React from "react";
import styles from "./styles.module.css";

type BaseTextProps = React.ComponentProps<typeof Title>;

const CourseCardTitle: React.FC<BaseTextProps> = ({ className, ...props }) => (
  <Title {...props} className={`${styles.title} ${className ?? ""}`} />
);

export default CourseCardTitle;
