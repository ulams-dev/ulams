import { Text } from "@ulams/components/components/atoms/Typography/Text";
import React from "react";
import styles from "./TechnicalMaintenanceScreen.module.css";
import { isMobile } from "react-device-detect";

type Props = {
  text?: string;
};

const TechnicalMaintenanceScreen: React.FC<Props> = ({ text }) => {
  return (
    <div
      className={`${styles.container} ${isMobile ? styles.mobile : ""}`}
    >
      <div>
        <img src={`/images/maintenance-bg.svg`} alt=" " />
      </div>
      <div>
        {text ? (
          <Text>{text}</Text>
        ) : (
          <Text>
            Dostęp do platformy ograniczony z powodu trwających prac
            technicznych
            <br />
            <br />
            Przepraszamy za utrudnienia. Zapraszamy wkrótce
          </Text>
        )}
      </div>
    </div>
  );
};

export default TechnicalMaintenanceScreen;
