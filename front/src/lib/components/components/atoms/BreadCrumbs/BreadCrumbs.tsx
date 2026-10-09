import * as React from "react";

import { getUniqueId } from "../../../utils/utils";
import { cx } from "../../../utils/cx";
import styles from "./BreadCrumbs.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { legacyDefault } from "../../../utils/legacy";

interface BreadCrumbsProps extends ExtendableStyledComponent {
  items: React.ReactNode[];
  hyphen?: React.ReactNode;
}

const HyphenIcon = () => (
  <svg
    width="5"
    height="7"
    viewBox="0 0 5 7"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      d="M0.872039 6.29471C0.70932 6.13199 0.70932 5.86817 0.872039 5.70545L3.07741 3.50008L0.872039 1.29471C0.70932 1.13199 0.70932 0.868172 0.872039 0.705454C1.03476 0.542735 1.29858 0.542735 1.46129 0.705454L3.96129 3.20545C4.12401 3.36817 4.12401 3.63199 3.96129 3.79471L1.46129 6.29471C1.29858 6.45743 1.03476 6.45743 0.872039 6.29471Z"
      fill="#AFAFAF"
    />
  </svg>
);

export const BreadCrumbs: React.FC<BreadCrumbsProps> = ({
  items,
  className = "",
  hyphen = <HyphenIcon />,
}) => {
  return (
    <nav
      className={cx(styles.nav, "ulams-component", className)}
      aria-label={getUniqueId("nav")}
    >
      <ul>
        {items.map((node, i) => (
          <React.Fragment key={`breadcrumb-${getUniqueId(String(node))}-${i}`}>
            <li itemScope itemType="http://data-vocabulary.org/Breadcrumb">
              {node}
            </li>
            <li>{i !== items.length - 1 && hyphen}</li>
          </React.Fragment>
        ))}
      </ul>
    </nav>
  );
};

export default legacyDefault(BreadCrumbs);
