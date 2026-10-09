import ProfileLayout from "@/components/Profile/ProfileLayout";
import { MyProfileForm } from "@ulams/components/components/organisms/MyProfileForm/MyProfileForm";
import { useTranslation } from "react-i18next";

import styles from "./user.module.css";

const MyData = () => {
  const { t } = useTranslation();
  return (
    <ProfileLayout title={t("MyProfilePage.EditData")}>
      <div className={styles.myDataWrapper}>
        <MyProfileForm />
      </div>
    </ProfileLayout>
  );
};

export default MyData;
