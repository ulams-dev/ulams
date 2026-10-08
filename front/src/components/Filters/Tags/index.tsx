import { FC, useCallback, useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import { isMobile } from "react-device-detect";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { CloseIcon } from "../../../icons";
import { FiltersState } from "@/types/filters";
import styles from "./FiltersTags.module.css";

interface FiltersTagsProps {
  filters: FiltersState;
  onReset: () => void;
}

const FiltersTags: FC<FiltersTagsProps> = ({ filters, onReset }) => {
  const { categoryTree } = useContext(UlamsContext);
  const isButton =
    !!filters?.categories?.length || !!filters?.name || !!filters?.tags?.length;

  const renderText = useCallback(
    (value: string) => <Text className={styles.tag}>{value}</Text>,
    []
  );

  return (
    <div className={`${styles.root} ${isMobile ? styles.mobile : ""}`}>
      <div className={styles.tagsList}>
        {!!filters?.categories &&
          categoryTree.list
            ?.filter((item) => filters.categories?.indexOf(item.id) > -1)
            .map((category) => renderText(category.name))}
        {!!filters?.tags && filters?.tags.map((tagName) => renderText(tagName))}
        {!!filters?.name && renderText(filters.name)}
      </div>
      {isButton && (
        <button
          type="button"
          onClick={() => {
            onReset();
          }}
          className={styles.clearBtn}
        >
          <CloseIcon />
        </button>
      )}
    </div>
  );
};

export default FiltersTags;
