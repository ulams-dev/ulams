import { Link } from "@ulams/components/components/atoms/Link/Link";
import React from "react";
import styles from "./styles.module.css";

type BaseLinkProps = React.ComponentProps<typeof Link>;

const NewLink: React.FC<BaseLinkProps> = ({ className, ...props }) => (
  <Link {...props} className={`${styles.link} ${className ?? ""}`} />
);

export default NewLink;
