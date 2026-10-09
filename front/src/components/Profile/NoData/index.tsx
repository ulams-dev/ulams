import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { isMobile } from "react-device-detect";
import styles from "./styles.module.css";
import { Link } from "react-router-dom";

interface Props {
  title: string;
  description: string;
  buttonText: string;
  buttonLocation: string;
}

const ProfileNoData = ({
  title,
  description,
  buttonText,
  buttonLocation,
}: Props) => {
  return (
    <div className={`${styles.noData} ${isMobile ? styles.mobile : ""}`}>
      <Title level={3}>{title}</Title>
      <Text className={styles.smallText}>{description}</Text>
      <Link to={buttonLocation}>
        <Button mode="secondary">{buttonText}</Button>
      </Link>
    </div>
  );
};

export default ProfileNoData;
