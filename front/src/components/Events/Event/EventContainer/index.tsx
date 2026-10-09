import { useContext } from "react";
import { Row, Col } from "react-grid-system";

import { UlamsContext } from "@ulams/sdk/react";
import Loader from "@/components/_App/Preloader";

import EventBreadcrumbs from "@/components/Events/Event/EventBreadcrumbs";
import EventInfo from "@/components/Events/Event/EventInfo";
import EventSidebar from "@/components/Events/Event/EventSidebar";
import EventTutor from "@/components/Events/Event/EventTutor";
import EventCompanies from "@/components/Events/Event/EventCompanies";
import EventDescription from "@/components/Events/Event/EventDescription";

import styles from "./EventContainer.module.css";
import EventAgenda from "@/components/Events/Event/EventAgenda";

const EventContainer = () => {
  const { stationaryEvent } = useContext(UlamsContext);

  if (stationaryEvent.loading || !stationaryEvent.value) {
    return <Loader />;
  }
  return (
    <div className={styles.root}>
      <Row>
        <Col md={12} lg={9}>
          <EventBreadcrumbs />
          <EventInfo />
          <EventCompanies />
          <EventTutor />
          <EventDescription />
          <EventAgenda />
        </Col>
        <Col md={12} lg={3}>
          <EventSidebar />
        </Col>
      </Row>
    </div>
  );
};
export default EventContainer;
