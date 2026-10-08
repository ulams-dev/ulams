import { useMemo } from "react";
import CategoriesFilter from "@/components/Filters/Categories";
import SearchFilter from "@/components/Filters/Search";
import FiltersTags from "@/components/Filters/Tags";
import { FiltersState } from "@/types/filters";
import { useSearchParams } from "../../../../../hooks/useSearchParams";
import styles from "./styles.module.css";

const ConsultationsHeaderFilters = () => {
  const {
    setPathname,
    setQueryParam,
    setQueryArrayParam,
    getAllQueryValueByName,
    getQueryValueByName,
  } = useSearchParams();
  const filters: FiltersState = useMemo(
    () => ({
      categories:
        getAllQueryValueByName("categories[]").map((val) => Number(val)) || [],
      name: getQueryValueByName("name") || "",
    }),
    [getAllQueryValueByName, getQueryValueByName]
  );

  return (
    <div className={styles.root}>
      <div className="tags">
        <FiltersTags
          filters={filters}
          onReset={() => {
            setPathname();
          }}
        />
      </div>
      <div className={styles.selectsRow}>
        <div className={`single-select ${styles.singleSelectSearch}`}>
          <SearchFilter
            onSubmit={(value) => {
              setQueryParam("name", value);
            }}
          />
        </div>
        <div className={`single-select ${styles.singleSelectCategory}`}>
          <CategoriesFilter
            selectedCategories={getAllQueryValueByName("categories[]")?.map(
              (catNumber) => Number(catNumber)
            )}
            handleChange={(categories) => {
              setQueryArrayParam("categories[]", categories);
            }}
          />
        </div>
      </div>
    </div>
  );
};

export default ConsultationsHeaderFilters;
