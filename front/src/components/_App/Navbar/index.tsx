import { useContext, useEffect, useMemo, useState } from "react";

import { UlamsContext } from "@ulams/sdk/react/context";
import { Navigation } from "@ulams/components/components/molecules/Navigation/Navigation";
import { Avatar } from "@ulams/components/components/atoms/Avatar/Avatar";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { SearchCourses } from "@ulams/components/components/organisms/SearchCourses/SearchCourses";
import { Link, NavLink, useHistory } from "react-router-dom";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import styles from "./Navbar.module.css";
import { isMobile } from "react-device-detect";
import {
  HamburguerIcon,
  HeaderCard,
  HeaderNotification,
  ProfileIcon,
} from "../../../icons";
import { useTranslation } from "react-i18next";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import Container from "@/components/Common/Container";
import routeRoutes from "@/components/Routes/routes";
import { DropdownMenu } from "@ulams/components";
import { DropdownMenuItem } from "@ulams/components/components/molecules/DropdownMenu/DropdownMenu";
import NotificationsDrawer from "@/components/Notifications/drawer";
import MobileDrawer from "@/components/_App/MobileDrawer";
import { ResponsiveImage } from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import useDeleteAccountModal from "@/hooks/useDeleteAccount";
import DeleteAccountModal from "@/components/Authentication/DeleteAccountModal";
import { isMobilePlatform } from "@/utils/index";
import { metaDataKeys } from "@/utils/meta";
import { VITE_APP_PUBLIC_IMG_BUCKET_FOLDER } from "@/config/index";

const Navbar = () => {
  const { t } = useTranslation();
  const {
    showModal,
    closeModal,
    loading,
    handleDeleteAccount,
    triggerDeleteAccount,
  } = useDeleteAccountModal();

  const {
    user: userObj,
    settings,
    logout,
    notifications,
    cart,
  } = useContext(UlamsContext);
  const user = userObj?.value;
  const history = useHistory();
  // Header icons pick their fill from the mode in JS.
  const mode = useThemeTokens()?.mode ?? "light";

  const [showNotifications, setShowNotifications] = useState(false);
  const [showMobileDrawer, setShowMobileDrawer] = useState(false);

  useEffect(() => {
    if (
      settings?.value?.onboarding?.isShown &&
      user &&
      user.id &&
      // @ts-ignore
      !user.isOnboardingCompleted
    ) {
      history.push(routeRoutes.onboarding);
    }
  }, [user, history, settings?.value?.onboarding?.isShown]);

  const displayCartItems = useMemo(() => {
    return cart?.value?.items?.length ?? 0;
  }, [cart]);

  const getProperLogoPath = useMemo(() => {
    const bucket = VITE_APP_PUBLIC_IMG_BUCKET_FOLDER.replace(/^\/|\/$/g, ""); // e.g. "/ulams" // -> "ulams"

    // Full link, e.g. "https://randomdomain/somefolder/folder/testimg.jpg"
    const url = settings?.value?.global?.logo;

    // 1. Extract pathname: "/ulams/avatars/testimg.jpg"
    if (!url) return null;
    let relativePath: string;
    try {
      // If url is absolute (starts with http(s) or protocol-relative //) use URL to extract pathname,
      // otherwise treat it as already a relative path.
      if (/^(https?:)?\/\//.test(url)) {
        relativePath = new URL(url).pathname;
      } else {
        relativePath = url;
      }
    } catch (e) {
      // On any parsing error, fall back to the original value
      relativePath = url;
    }

    // 2. Remove the bucket prefix
    const bucketPrefix = `/${bucket}`; // "/ulams"
    if (relativePath.startsWith(bucketPrefix)) {
      relativePath = relativePath.slice(bucketPrefix.length);
    }

    // 3. Make sure the result begins with one leading slash
    if (!relativePath.startsWith("/")) {
      relativePath = "/" + relativePath;
    }

    return relativePath;
  }, [settings?.value?.global?.logo]);

  const menuItems = [
    {
      title: (
        <Link to={routeRoutes.home}>
          <Text noMargin bold>
            {t("Menu.HomePage")}
          </Text>
        </Link>
      ),
      key: "menu-1",
    },
    {
      title: (
        <Link to={routeRoutes.courses}>
          <Text noMargin bold>
            {t("Menu.Courses")}
          </Text>
        </Link>
      ),
      key: "menu-2",
    },
    {
      title: (
        <Link to={routeRoutes.webinars}>
          <Text noMargin bold>
            {t("Menu.Webinars")}
          </Text>
        </Link>
      ),
      key: "menu-3",
    },
    {
      title: (
        <Link to={routeRoutes.courses}>
          <Text noMargin bold>
            {t("Menu.Consultations")}
          </Text>
        </Link>
      ),
      key: "menu-4",
    },
    {
      title: (
        <Link to={routeRoutes.subscriptions}>
          <Text noMargin bold>
            {t("MyProfilePage.Subscriptions")}
          </Text>
        </Link>
      ),
      key: "menu-5",
    },
    {
      title: settings?.value?.config?.[metaDataKeys.termsPageMetaKey] && (
        <Link
          to={`/${settings?.value?.config?.[metaDataKeys.termsPageMetaKey]}`}
        >
          <Text noMargin bold>
            {t("Terms")}
          </Text>
        </Link>
      ),
      key: "menu-6",
    },

    {
      title: user ? null : (
        <div className={styles.lastMobileMenuItem}>
          <Button
            mode={"primary"}
            block
            onClick={() => history.push(routeRoutes.login)}
          >
            {t("Header.Login")}
          </Button>
          <span>{t("Login.NoAccount")}</span>
          <Button
            mode={"outline"}
            block
            onClick={() => history.push(routeRoutes.register)}
          >
            {t("Login.Signup")}
          </Button>
        </div>
      ),
      key: "menuItem3",
    },
  ];

  if (isMobile) {
    return (
      <header className={`${styles.header} ${styles.mobile}`}>
        <Navigation
          mobile
          logo={
            <div className="logo-container">
              <Link to="/" aria-label={t("Go to the main page")}>
                <ResponsiveImage
                  path={getProperLogoPath || ""}
                  srcSizes={[100, 200, 300]}
                />
              </Link>
            </div>
          }
          cart={
            user?.id && !isMobilePlatform ? (
              <div className="icons-container">
                <button
                  type="button"
                  className="cart-icon cart"
                  onClick={() => history.push(routeRoutes.cart)}
                  data-tooltip={String(cart?.value?.items.length)}
                  aria-label={t("CoursePage.GoToCheckout")}
                >
                  <HeaderCard mode={mode} />

                  {cart && (cart?.value?.items?.length ?? 0) > 0 ? (
                    <span>{displayCartItems}</span>
                  ) : null}
                </button>
              </div>
            ) : null
          }
          notification={
            user?.id ? (
              <div className="icons-container">
                <button
                  type="button"
                  className="cart-icon"
                  onClick={() => history.push(routeRoutes.myNotifications)}
                  data-tooltip={String(notifications.list?.meta.total)}
                  aria-label={t("CoursePage.Notifications")}
                >
                  <HeaderNotification mode={mode} />
                  {notifications.list?.meta.total &&
                  notifications.list?.meta.total > 0 ? (
                    <span>{notifications.list?.meta.total}</span>
                  ) : null}
                </button>
              </div>
            ) : null
          }
          profile={
            user?.id ? (
              <div className="icons-container">
                <button
                  type="button"
                  className="cart-icon"
                  onClick={() => setShowMobileDrawer(true)}
                  aria-label={t("CoursePage.GoToCheckout")}
                >
                  {!!user?.avatar ? (
                    <Avatar
                      src={user.avatar}
                      alt={user.first_name}
                      size={"superSmall"}
                      className="user-avatar"
                    />
                  ) : (
                    <ProfileIcon mode={mode} />
                  )}
                </button>
              </div>
            ) : null
          }
          menuItems={menuItems}
        />
        <div className={styles.searchMobileWrapper}>
          <SearchCourses
            onItemSelected={(item) => history.push(`/courses/${item.id}`)}
            onInputSubmitted={(input) =>
              history.push(`/courses/?title=${input}`)
            }
          />
        </div>
        <MobileDrawer
          isOpen={showMobileDrawer}
          onClose={() => setShowMobileDrawer(false)}
          height={"62vh"}
        >
          <div className={styles.mobileDrawerNavigation}>
            <ul>
              <li>
                <NavLink to={routeRoutes.myProfile}>
                  {t("Navbar.MyCourses")}
                </NavLink>
              </li>
              <li>
                <NavLink to={routeRoutes.myConsultations}>
                  {t("MyProfilePage.MyConsultations")}
                </NavLink>
              </li>
              <li>
                <NavLink to={routeRoutes.myWebinars}>
                  {t("MyProfilePage.MyWebinars")}
                </NavLink>
              </li>
              <li>
                <NavLink to={routeRoutes.myCertificates}>
                  {t("Navbar.MyCertificates")}
                </NavLink>
              </li>
              <>
                <li>
                  <NavLink to={routeRoutes.mySubscriptions}>
                    {t("MyProfilePage.Subscriptions")}
                  </NavLink>
                </li>
              </>
              <>
                <li>
                  <NavLink to={routeRoutes.myOrders}>
                    {t("Navbar.MyOrders")}
                  </NavLink>
                </li>
              </>
              <li>
                <NavLink to={routeRoutes.myData}>
                  {t("Navbar.EditProfile")}
                </NavLink>
              </li>
              {settings?.value?.config?.[metaDataKeys.termsPageMetaKey] && (
                <li>
                  <NavLink
                    to={`/${
                      settings?.value?.config?.[metaDataKeys.termsPageMetaKey]
                    }`}
                  >
                    {t("Terms")}
                  </NavLink>
                </li>
              )}
              <li>
                <button
                  onClick={() =>
                    logout().then(() => history.push(routeRoutes.home))
                  }
                >
                  {t("Navbar.Logout")}
                </button>
              </li>
              <li>
                <button
                  className="delete-account"
                  onClick={() => triggerDeleteAccount()}
                >
                  {t("MyProfilePage.DeleteAccount")}
                </button>
              </li>
            </ul>
          </div>
        </MobileDrawer>
        <DeleteAccountModal
          closeModal={() => closeModal()}
          showModal={showModal}
          handleDeleteAccount={() => {
            history.push(routeRoutes.home);
            closeModal();
            setShowMobileDrawer(false);
            handleDeleteAccount();
          }}
          isLoading={loading}
        />
      </header>
    );
  }

  return (
    <header className={styles.header}>
      <Container
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          width: "100%",
        }}
      >
        <div className="logo-container">
          <Link to="/" aria-label={t("Go to the main page")}>
            <ResponsiveImage
              path={getProperLogoPath || ""}
              srcSizes={[50, 100, 150]}
            />
          </Link>
        </div>
        <div className="search-container">
          <SearchCourses
            onItemSelected={(item) => history.push(`/courses/${item.id}`)}
            onInputSubmitted={(input) =>
              history.push(`/courses/?title=${input}`)
            }
          />
        </div>
        <div className="menu-container">
          <nav className="navigation">
            <DropdownMenu
              menuItems={[
                {
                  id: 1,
                  content: t("Menu.HomePage"),
                  redirect: routeRoutes.home,
                },

                {
                  id: 2,
                  content: t("Menu.Courses"),
                  redirect: routeRoutes.courses,
                },
                {
                  id: 3,
                  content: t("Menu.Consultations"),
                  redirect: routeRoutes.consultations,
                },
                {
                  id: 4,
                  content: t("MyProfilePage.Subscriptions"),
                  redirect: routeRoutes.subscriptions,
                },
                {
                  id: 5,
                  content: t("Terms"),
                  redirect: `/${
                    settings?.value?.config?.[metaDataKeys.termsPageMetaKey]
                  }`,
                },
                // {
                //   id: 3,
                //   content: t("Menu.Tutors"),
                //   redirect: routeRoutes.tutors,
                // },
                // {
                //   id: 4,
                //   content: t("Menu.Consultations"),
                //   redirect: routeRoutes.consultations,
                // },
                // {
                //   id: 5,
                //   content: t("Menu.Events"),
                //   redirect: routeRoutes.events,
                // },
                {
                  id: 6,
                  content: t("Menu.Webinars"),
                  redirect: routeRoutes.webinars,
                },
                // {
                //   id: 7,
                //   content: t("Menu.Packages"),
                //   redirect: routeRoutes.packages,
                // },
              ]}
              onChange={(e: DropdownMenuItem) => {
                if (e.redirect && e.redirect !== "") {
                  history.push(e?.redirect);
                }
              }}
              child={
                <Button mode="icon" className="dropdown">
                  Menu <HamburguerIcon />
                </Button>
              }
            />
            {/* TODO: set this to admin panel and show it conditionally */}
            {/* <DropdownMenu
              menuItems={[
                {
                  id: "pl",
                  content: "Polski",
                },
                {
                  id: "en",
                  content: "English",
                },
              ]}
              onChange={(e: DropdownMenuItem) =>
                handleLanguageChange({
                  label: String(e.content),
                  value: String(e.id),
                })
              }
              child={
                <Button mode="icon" className="dropdown">
                  {t("Menu.Language")} <LanguageIcon mode={mode} />
                </Button>
              }
            /> */}

            {user && (
              <div className="icons-container">
                <button
                  type="button"
                  className="cart-icon"
                  onClick={() => history.push(routeRoutes.cart)}
                  data-tooltip={String(cart?.value?.items.length ?? 0)}
                  aria-label={t("CoursePage.GoToCheckout")}
                >
                  <HeaderCard mode={mode} />

                  {(cart?.value?.items?.length ?? 0) > 0 ? (
                    <span>{displayCartItems}</span>
                  ) : null}
                </button>
              </div>
            )}

            {user && (
              <div className="icons-container">
                <button
                  type="button"
                  className="cart-icon"
                  onClick={() => setShowNotifications(!showNotifications)}
                  data-tooltip={String(notifications.list?.meta.total)}
                  aria-label={t("CoursePage.GoToCheckout")}
                >
                  <HeaderNotification mode={mode} />
                  {notifications.list?.meta.total &&
                  notifications.list?.meta.total > 0 ? (
                    <span>{notifications.list?.meta.total}</span>
                  ) : null}
                </button>
              </div>
            )}

            {user?.id && (
              <DropdownMenu
                menuItems={[
                  {
                    id: 1,
                    content: t("Navbar.MyProfile"),
                    redirect: routeRoutes.myOrders,
                  },
                  {
                    id: 2,
                    content: t("Navbar.MyCourses"),
                    redirect: routeRoutes.myProfile,
                  },

                  // {
                  //   id: 2,
                  //   content: t("Navbar.MyOrders"),
                  //   redirect: routeRoutes.myOrders,
                  // },
                  {
                    id: 3,
                    content: t("Navbar.MyConsultations"),
                    redirect: routeRoutes.myConsultations,
                  },
                  {
                    id: 4,
                    content: t("Navbar.MyWebinars"),
                    redirect: routeRoutes.myWebinars,
                  },
                  // {
                  //   id: 5,
                  //   content: t("Navbar.MyStationaryEvents"),
                  //   redirect: routeRoutes.myStationaryEvents,
                  // },
                  // {
                  //   id: 6,
                  //   content: t("Navbar.MyTasks"),
                  //   redrect: routeRoutes.myTasks,
                  // },
                  // {
                  //   id: 7,
                  //   content: t("Navbar.MyBookmarks"),
                  //   redirect: routeRoutes.myBookmarks,
                  // },
                  // {
                  //   id: 8,
                  //   content: t("Menu.Notifications"),
                  //   redirect: routeRoutes.myNotifications,
                  // },
                  // {
                  //   id: 9,
                  //   content: t("Navbar.EditProfile"),
                  //   redirect: routeRoutes.myData,
                  // },
                  {
                    id: 10,
                    content: t("Navbar.Logout"),
                    redirect: routeRoutes.logout,
                  },
                ]}
                onChange={(e: DropdownMenuItem) =>
                  e.redirect && e.redirect !== "logout"
                    ? history.push(e.redirect)
                    : logout().then(() => history.push(routeRoutes.home))
                }
                child={
                  <Button mode="icon" className="dropdown">
                    {!!user?.avatar ? (
                      <Avatar
                        src={user.avatar}
                        alt={user.first_name}
                        size={"superSmall"}
                        className="user-avatar"
                      />
                    ) : (
                      <ProfileIcon mode={mode} />
                    )}
                  </Button>
                }
              />
            )}
          </nav>

          {!user?.id && (
            <div className="not-logged-container">
              <Button
                mode="secondary"
                onClick={() => history.push(routeRoutes.login)}
              >
                {t("Header.Login")}
              </Button>

              <Button
                mode="secondary outline"
                onClick={() => history.push(routeRoutes.register)}
              >
                {t("Header.Register")}
              </Button>
            </div>
          )}
        </div>{" "}
      </Container>
      <NotificationsDrawer
        isOpen={showNotifications}
        onClose={() => setShowNotifications(false)}
      />
    </header>
  );
};

export default Navbar;
