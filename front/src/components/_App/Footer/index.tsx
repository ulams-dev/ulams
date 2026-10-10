import React, { useContext, useEffect, useMemo } from "react";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { UlamsContext } from "@ulams/sdk/react";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import { PageListItem, PaginatedMetaList } from "@ulams/sdk/types";
import { Link as LmsLink } from "@ulams/components/components/atoms/Link/Link";
import { Link } from "react-router-dom";
import Container from "@/components/Common/Container";
import routeRoutes from "@/components/Routes/routes";
import { UlamsLogo } from "@/icons/index";
import GoTop from "@/components/_App/GoTop";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { EU_BANNER_LINK } from "@/utils/constants";
import EuBanner from "../../../images/eu-banner.png";
import styles from "./Footer.module.css";

type LinkObject = {
  link: string | undefined;
  label: string | Record<string, string>;
};

const Footer = () => {
  const { settings, fetchPages, pages, user } = useContext(UlamsContext);
  const { t, i18n } = useTranslation();
  useEffect(() => {
    fetchPages();

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const footerFromApi = useMemo(
    () =>
      (settings?.value?.footerMenu?.menu ?? []).filter(
        (item: Record<string, string | Record<string, string>>) =>
          user.value ? item : !item.auth
      ),
    [settings?.value?.footerMenu?.menu, user.value]
  );

  const chunkArray = (
    arr: PaginatedMetaList<PageListItem> | undefined,
    chunkSize: number
  ) => {
    if (!arr) return [];
    const tempArray = [];
    for (let i = 0; i < arr.data.length; i += chunkSize) {
      tempArray.push(arr.data.slice(i, i + chunkSize));
    }
    return tempArray;
  };

  const getLogoTypesText = useMemo(() => {
    if (settings?.value?.footer_logotypes) {
      return settings?.value?.footer_logotypes?.text;
    } else {
      return null;
    }
  }, [settings?.value?.footer_logotypes]);

  const getLogotypes = useMemo(() => {
    if (settings?.value?.footer_logotypes) {
      const data = settings?.value?.footer_logotypes;

      const logotypes = Object.keys(data)
        .filter((key) => {
          if (!isNaN(Number(key))) {
            return data[key];
          }
        })
        .map((key) => data[key]);

      return logotypes.map((logo: string, index: number) => (
        <img key={index} src={logo} alt="logotype" />
      ));
    } else {
      return null;
    }
  }, [settings?.value?.footer_logotypes]);

  return (
    <footer className={`${styles.footer} ${isMobile ? styles.mobile : ""}`}>
      <Container>
        <div className={styles.linksRow}>
          {footerFromApi && footerFromApi.length > 0 ? (
            <>
              {footerFromApi.map((link: LinkObject) => {
                return (
                  !!link.link && (
                    <LmsLink
                      key={link.link.toString()}
                      className={styles.singleLink}
                      href={link.link}
                    >
                      {typeof link.label === "object" && (
                        <Text size="14">{link.label[i18n.language]}</Text>
                      )}
                    </LmsLink>
                  )
                );
              })}
            </>
          ) : (
            <>
              <Link className={styles.singleLink} to={routeRoutes.home}>
                <Text size="16">{t("Footer.HomePage")}</Text>
              </Link>
              <Link className={styles.singleLink} to={routeRoutes.courses}>
                <Text size="16">{t("Footer.Courses")}</Text>
              </Link>
              {user.value ? (
                <Link className={styles.singleLink} to={routeRoutes.myProfile}>
                  <Text size="16">{t("Footer.UserProfile")}</Text>
                </Link>
              ) : (
                <>
                  <Link className={styles.singleLink} to={routeRoutes.login}>
                    <Text size="16">{t("Header.Login")}</Text>
                  </Link>
                  <Link className={styles.singleLink} to={routeRoutes.register}>
                    <Text size="16">{t("Header.Register")}</Text>
                  </Link>
                </>
              )}
              <Link className={styles.singleLink} to={routeRoutes.cart}>
                <Text size="16">{t("Footer.Cart")}</Text>
              </Link>
            </>
          )}
        </div>
      </Container>
      <div className={styles.divider} />
      <Container>
        <div className={`${styles.linksRow} ${styles.pages}`}>
          {chunkArray(pages.list, 4).map((chunk: PageListItem[]) => (
            <div className={styles.chunkPages} key={chunk.toString()}>
              {chunk
                .filter((page: PageListItem) => !page.slug.includes("mobile"))
                .map((page: PageListItem) => (
                  <LmsLink
                    key={page.id}
                    className={styles.singleLink}
                    href={`/#/${page.slug}`}
                  >
                    <Text size="14">{page.title}</Text>
                  </LmsLink>
                ))}
            </div>
          ))}
        </div>
        {getLogoTypesText && (
          <div className={styles.logotypesText}>
            <MarkdownRenderer>{getLogoTypesText}</MarkdownRenderer>
          </div>
        )}
        {getLogotypes && <div className={styles.logotypes}>{getLogotypes}</div>}

        <div className={styles.euBanner}>
          <a href={EU_BANNER_LINK} target="_blank" rel="noopener noreferrer">
            <img
              className={styles.euBannerImg}
              src={EuBanner}
              alt="tutor_avatar"
            />
          </a>
        </div>

        <div className={styles.copyrights}>
          <Text size="14">{t("Footer.PoweredBy")}</Text>
          <LmsLink href="https://www.ulams.app">
            <UlamsLogo className={styles.logoLight} />
            <UlamsLogo className={styles.logoDark} reversed />
          </LmsLink>
        </div>
      </Container>
      <GoTop />
    </footer>
  );
};

export default Footer;
