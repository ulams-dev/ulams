import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import CategoriesSection from "../../../Categories/CategoriesSection";
import WebinarsHeader from "../WebinarsHeader";
import WebinarsContainerItems from "./Items";

const WebinarsContainer = () => {
  const { categoryTree } = useContext(UlamsContext);

  return (
    <>
      <WebinarsHeader />
      <WebinarsContainerItems />
      {categoryTree && (
        <>
          <CategoriesSection
            categories={
              categoryTree.list?.filter((category) => !!category.icon) || []
            }
            entity="webinars"
          />
        </>
      )}
    </>
  );
};

export default WebinarsContainer;
