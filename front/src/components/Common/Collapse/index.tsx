import React, { ReactNode, useState } from "react";
import { Checkbox } from "@ulams/components/components/atoms/Option/Checkbox";
import styles from "./styles.module.css";

type Props = {
  title: string;
  children: ReactNode;
  active?: boolean;
  onClick?: () => void;
};

const Collapse: React.FC<Props> = ({ title, children, active, onClick }) => {
  const [isOpened, setIsOpened] = useState(active || false);
  return (
    <div className={styles.collapse}>
      <div className="collapse-title">
        <Checkbox
          name={title}
          label={<strong>{title}</strong>}
          checked={active || isOpened}
          onChange={() => [setIsOpened(!isOpened), onClick && onClick()]}
        />
      </div>
      {(active || isOpened) && (
        <div className={`collapse-content ${styles.content}`}>{children}</div>
      )}
    </div>
  );
};

export default Collapse;
