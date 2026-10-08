import React, { useCallback, useContext } from "react";
import { Avatar } from "@ulams/components/components/atoms/Avatar/Avatar";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { UlamsContext } from "@ulams/sdk/react";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import styles from "./styles.module.css";

type Props = {
  size?: "small" | "extraSmall";
};

const AvatarUpload: React.FC<Props> = ({ size }) => {
  const { updateAvatar, user } = useContext(UlamsContext);
  const { t } = useTranslation();

  const handleAvatarChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target?.files?.[0];
      if (!file) return;

      updateAvatar(file);
    },
    [updateAvatar]
  );

  return (
    <div className={`${styles.container} ${isMobile ? styles.mobile : ""}`}>
      <Avatar size={size} src={user.value?.avatar} alt="" />
      <label htmlFor="fileInput">
        <Text className={styles.uploadText} size="12">
          {t("AddNewPhoto")}
        </Text>
        <input
          type="file"
          id="fileInput"
          accept="image/*"
          style={{ display: "none" }}
          onChange={handleAvatarChange}
        />
      </label>
    </div>
  );
};

export default AvatarUpload;
