import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import CategoriesSection from "../../../Categories/CategoriesSection";
import PackagesHeader from "../PackagesHeader";
import PackagesContainerItems from "./Items";

const PackagesContainer = () => {
  const { categoryTree } = useContext(UlamsContext);

  return (
    <>
      <PackagesHeader />
      <PackagesContainerItems />
      {categoryTree && (
        <CategoriesSection
          categories={
            categoryTree.list?.filter((category) => !!category.icon) || []
          }
          entity="packages"
        />
      )}
    </>
  );
};

export default PackagesContainer;
