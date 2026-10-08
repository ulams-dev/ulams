import { useContext } from "react";
import { EscolaLMSContext } from "@lms/sdk/react";
import EventDetailsSidebar from "@/components/Events/Event/EventDetailsSidebar";
import { DetailsSidebarContainer } from "@/components/DetailsSidebarContainer";

const EventSidebar = () => {
  const { stationaryEvent } = useContext(EscolaLMSContext);

  if (!stationaryEvent.value) {
    return null;
  }
  return (
    <DetailsSidebarContainer>
      <EventDetailsSidebar event={stationaryEvent.value} />
    </DetailsSidebarContainer>
  );
};

export default EventSidebar;
