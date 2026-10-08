import Layout from "@/components/_App/Layout";
import { useHistory } from "react-router-dom";
import { useTranslation } from "react-i18next";
import styles from "./NotFound.module.css";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import Container from "@/components/Common/Container";
import routeRoutes from "@/components/Routes/routes";

const Custom404 = () => {
  const { t } = useTranslation();
  const history = useHistory();
  return (
    <Layout>
      <div className={styles.root}>
        <Container>
          <div className={styles.content}>
            <Title level={3}>{t("Custom404Page.Info")}</Title>
            <Text>{t("Custom404Page.NotFound")}</Text>
            <Button onClick={() => history.push(routeRoutes.home)}>
              {t("Home")}
            </Button>
          </div>
        </Container>
      </div>
    </Layout>
  );
};

export default Custom404;
