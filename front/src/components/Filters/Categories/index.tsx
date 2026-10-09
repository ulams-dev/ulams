import { FC, useContext } from "react";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import { UlamsContext } from "@ulams/sdk/react";
import Categories from "@ulams/components/components/molecules/Categories/Categories";
import Title from "@ulams/components/components/atoms/Typography/Title";

interface CategoriesFilterProps {
  selectedCategories: number[];
  handleChange: (categories: number[]) => void;
}

const CategoriesFilter: FC<CategoriesFilterProps> = ({
  selectedCategories,
  handleChange,
}) => {
  const { categoryTree } = useContext(UlamsContext);
  const theme = useThemeTokens();
  const { t } = useTranslation();

  return (
    <Categories
      mobile={isMobile}
      backgroundColor={theme?.primaryColor}
      categories={categoryTree.list || []}
      label={t("Filters.Category")}
      selectedCategories={selectedCategories}
      drawerTitle={
        <Title
          level={5}
          style={{
            fontSize: "14px",
          }}
        >
          {t("Filters.Category")}
        </Title>
      }
      handleChange={handleChange}
    />
  );
};

export default CategoriesFilter;
