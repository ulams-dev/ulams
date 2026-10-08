import Container from "@/components/Common/Container";
import styles from "./Layouts.module.css";
import { Title } from "@ulams/components";

type Props = {
  children: React.ReactNode;
  title: string;
};

const EntityPageWrapper: React.FC<Props> = ({ children, title }) => {
  return (
    <section className={`consultations-page ${styles.wrapper}`}>
      <div className={styles.header}>
        <Container>
          <Title level={1}> {title}</Title>
        </Container>
      </div>
      <Container>{children}</Container>
    </section>
  );
};

export default EntityPageWrapper;
