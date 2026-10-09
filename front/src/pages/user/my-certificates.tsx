import ProfileLayout from "@/components/Profile/ProfileLayout";
import { useTranslation } from "react-i18next";
import ProfileCertificates from "@/components/Profile/ProfileCertificates";

import styles from "./user.module.css";

const MyCertificates = () => {
  const { t } = useTranslation();
  return (
    <ProfileLayout title={t("MyProfilePage.MyCertificates")}>
      <div className={styles.certificatesWrapper}>
        <ProfileCertificates />
      </div>
    </ProfileLayout>
  );
};

export default MyCertificates;
