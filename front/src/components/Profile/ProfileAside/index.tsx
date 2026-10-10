import { UlamsContext } from "@ulams/sdk/react";
import React, { useContext, useEffect } from "react";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { NavLink, useHistory } from "react-router-dom";
import UserSidebar from "@/components/Profile/UserSidebar";
import { UserIcon } from "../../../icons";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import routeRoutes from "@/components/Routes/routes";

import DeleteAccountModal from "@/components/Authentication/DeleteAccountModal";
import useDeleteAccountModal from "@/hooks/useDeleteAccount";
import { metaDataKeys } from "@/utils/meta";
import styles from "./styles.module.css";

export type NavigationTab = {
  title: string;
  key: string;
  url: string;
};

type Props = {
  tabs: NavigationTab[];
  isProfile?: boolean;
};

const ProfileAside: React.FC<Props> = ({ tabs, isProfile = true }) => {
  const { logout, fetchProgress, settings } = useContext(UlamsContext);
  const {
    triggerDeleteAccount,
    handleDeleteAccount,
    showModal,
    closeModal,
    loading,
  } = useDeleteAccountModal();
  const { t } = useTranslation();

  const history = useHistory();

  useEffect(() => {
    fetchProgress();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className={styles.asideWrapper}>
      {isProfile && (
        <Title level={2} as="h2">
          {t("MyProfilePage.YourAccount")}
        </Title>
      )}

      <aside className={`${styles.aside} ${isMobile ? styles.mobile : ""}`}>
        <div className={styles.userMainSidebar}>
          <UserSidebar icon={<UserIcon />}>
            <nav className={styles.navigation}>
              {tabs.map((item) => (
                <NavLink
                  activeClassName="selected"
                  to={item.url}
                  key={item.key}
                >
                  <Text size="16">{item.title}</Text>
                </NavLink>
              ))}

              {isProfile &&
                settings?.value?.config?.[metaDataKeys.termsPageMetaKey] && (
                  <NavLink
                    to={`/${
                      settings.value.config?.[metaDataKeys.termsPageMetaKey]
                    }`}
                  >
                    <Text size="16">{t("Terms")}</Text>
                  </NavLink>
                )}
            </nav>
          </UserSidebar>
        </div>
      </aside>
      {isProfile && (
        <aside className={`${styles.aside} ${isMobile ? styles.mobile : ""}`}>
          <div className={styles.userMainSidebar}>
            <UserSidebar>
              <div className={styles.navigation}>
                <button
                  onClick={() =>
                    logout().then(() => history.push(routeRoutes.home))
                  }
                >
                  <Text>{t("MyProfilePage.Logout")}</Text>
                </button>
                <button
                  className={styles.deleteAccount}
                  onClick={() => triggerDeleteAccount()}
                >
                  <Text>{t("MyProfilePage.DeleteAccount")}</Text>
                </button>
              </div>
            </UserSidebar>
          </div>
        </aside>
      )}
      <DeleteAccountModal
        closeModal={closeModal}
        showModal={showModal}
        handleDeleteAccount={handleDeleteAccount}
        isLoading={loading}
      />
    </div>
  );
};

export default ProfileAside;
