import styles from "./Loader.module.css";
import Layout from "@/components/_App/Layout";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { useTranslation } from "react-i18next";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";

export const Loader = () => {
  const { t } = useTranslation();
  return (
    <Layout>
      <div className={styles.root}>
        <Title level={3}> {t("Loading")}</Title>
        <Spin />
      </div>
    </Layout>
  );
};
