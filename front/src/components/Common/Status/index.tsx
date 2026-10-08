import { useMemo } from "react";
import styles from "./styles.module.css";

export enum StatusTypes {
  "ACCEPTED",
  "WAITING",
  "ENDED",
  "CANCELED",
  "DEFAULT",
}

interface Props {
  status: StatusTypes;
  name: string;
}

const Status = ({ status, name }: Props) => {
  const color = useMemo(() => {
    switch (status) {
      case StatusTypes.ACCEPTED:
        return "#198754";
      case StatusTypes.WAITING:
        return "#FFC300";
      case StatusTypes.ENDED:
      case StatusTypes.CANCELED:
        return "#D22B2B";
      default:
        return "var(--ulams-color-primary)";
    }
  }, [status]);

  return (
    <div className={styles.root}>
      <div
        className={styles.status}
        style={{
          backgroundColor: color,
        }}
      />
      <div className={styles.name}>{name}</div>
    </div>
  );
};

export default Status;
