import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import EventDetailsSidebar from "@/components/Events/Event/EventDetailsSidebar";
import { DetailsSidebarContainer } from "@/components/DetailsSidebarContainer";

const EventSidebar = () => {
  const { stationaryEvent } = useContext(UlamsContext);

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
