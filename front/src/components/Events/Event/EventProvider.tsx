import React, { useContext, useEffect } from "react";

import { UlamsContext } from "@ulams/sdk/react/context";
import { useParams } from "react-router-dom";

export const EventContext: React.Context<{}> = React.createContext({});

const EventProvider: React.FC<{
  children: React.ReactNode;
}> = ({ children }) => {
  const { id } = useParams<{ id: string }>();
  const { fetchStationaryEvent } = useContext(UlamsContext);

  useEffect(() => {
    if (id) {
      fetchStationaryEvent(Number(id));
    }
  }, [fetchStationaryEvent, id]);

  return <EventContext.Provider value={{}}>{children}</EventContext.Provider>;
};

export default EventProvider;
