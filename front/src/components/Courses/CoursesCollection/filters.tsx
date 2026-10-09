import React from "react";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { DropdownCategories, DropdownMenu } from "@ulams/components";
import { isMobile } from "react-device-detect";
import { ArrowDown, IconSquares } from "@/icons/index";
import { Category, CourseParams } from "@ulams/sdk/types";
import {
  MobileDrawerTypes,
  SortOrder,
} from "@/components/Courses/CoursesCollection";
import { DropdownMenuItem } from "@ulams/components/components/molecules/DropdownMenu/DropdownMenu";
import { useTranslation } from "react-i18next";
import styles from "./filters.module.css";

type Props = {
  prevCategories: Category[];
  onClearCategories: () => void;
  handleCategoryChange: (category: Category) => void;
  categories: Category[];
  handleSortChange: (type: SortOrder) => void;
  params: CourseParams | undefined;
  setMobileDrawerState: (state: {
    showDrawer: boolean;
    type: keyof typeof MobileDrawerTypes;
  }) => void;
  parentState: {
    showDrawer: boolean;
    type: keyof typeof MobileDrawerTypes;
  };
};

const CoursesFilters: React.FC<Props> = ({
  prevCategories,
  onClearCategories,
  handleCategoryChange,
  categories,
  handleSortChange,
  params,
  setMobileDrawerState,
  parentState,
}) => {
  const { t } = useTranslation();

  return (
    <div className={styles.filtersHeader}>
      {isMobile ? (
        <button
          type="button"
          className={`${styles.dropdownCategoriesButton} ${styles.buttonReset}`}
          aria-expanded={parentState.showDrawer}
          onClick={() =>
            setMobileDrawerState({
              showDrawer: !parentState.showDrawer,
              type: MobileDrawerTypes.categories,
            })
          }
        >
          <IconSquares />
          <Text size="16">{t("CoursesPage.showByCategory")}</Text>
          <ArrowDown />
        </button>
      ) : (
        <DropdownCategories
          checkedCategories={prevCategories}
          onClear={onClearCategories}
          onChange={handleCategoryChange}
          categories={categories || []}
          child={
            <div className={styles.dropdownCategoriesButton}>
              <IconSquares />
              <Text size="16">{t("CoursesPage.showByCategory")}</Text>
              <ArrowDown />
            </div>
          }
        />
      )}

      <div className={styles.sortWrapper}>
        <Text
          onClick={() =>
            isMobile &&
            setMobileDrawerState({
              showDrawer: !parentState.showDrawer,
              type: MobileDrawerTypes.sort,
            })
          }
        >
          {t("CoursesPage.sort")} {isMobile && <ArrowDown />}
        </Text>
        {!isMobile && (
          <DropdownMenu
            top={10}
            menuItems={[
              {
                id: "DESC",
                content: t("CoursesPage.newOnes"),
              },
              {
                id: "ASC",
                content: t("CoursesPage.oldOnes"),
              },
            ]}
            onChange={(e: DropdownMenuItem) =>
              handleSortChange(String(e.id) as SortOrder)
            }
            child={
              <Text>
                {params && params.order === "DESC"
                  ? t("CoursesPage.newOnes")
                  : t("CoursesPage.oldOnes")}
                <ArrowDown />
              </Text>
            }
          />
        )}
      </div>
    </div>
  );
};

export default CoursesFilters;
