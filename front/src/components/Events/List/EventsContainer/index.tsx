import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import CategoriesSection from "../../../Categories/CategoriesSection";
import EventsHeader from "../EventsHeader";
import EventsContainerItems from "./Items";

const EventsContainer = () => {
  const { categoryTree } = useContext(UlamsContext);

  return (
    <>
      <EventsHeader />
      <EventsContainerItems />
      {categoryTree && (
        <>
          <CategoriesSection
            categories={
              categoryTree.list?.filter((category) => !!category.icon) || []
            }
            entity="events"
          />
        </>
      )}
    </>
  );
};

export default EventsContainer;
