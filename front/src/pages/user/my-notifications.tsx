import Layout from "@/components/_App/Layout";
import Container from "@/components/Common/Container";
import Notifications from "@/components/Notifications";

import styles from "./user.module.css";

const MyNotificationsPage = () => {
  return (
    <Layout>
      <div className={styles.notificationsWrapper}>
        <Container>
          <div className={styles.notificationsContainer}>
            <Notifications />
          </div>
        </Container>
      </div>
    </Layout>
  );
};

export default MyNotificationsPage;
