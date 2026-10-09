import { useCallback, useRef } from "react";
import { BreadCrumbs } from "@ulams/components/components/atoms/BreadCrumbs/BreadCrumbs";
import styles from "./CategoriesBreadCrumbs.module.css";

export interface CategoriesProps {
  categories: Ulams.Categories.Models.Category[] | undefined;
  onCategoryClick?: (id: number) => void;
}

const CategoriesBreadCrumbs = (props: CategoriesProps) => {
  const { categories, onCategoryClick } = props;
  // const [open, setOpen] = useState(false);
  const parentRef = useRef<HTMLDivElement | null>(null);
  const firstCategories = categories || [];
  // const otherCategories = categories ? [...categories].splice(2) : [];

  const categoryClick = useCallback(
    (id: number) => {
      if (onCategoryClick) {
        onCategoryClick(id);
      }
    },
    [onCategoryClick]
  );

  return (
    <div className={styles.root} ref={parentRef}>
      <BreadCrumbs
        hyphen=""
        items={firstCategories?.map((category, index) => (
          <>
            <span
              className={styles.categoryName}
              key={category.name + index}
              onClick={() => categoryClick(category.id)}
              aria-hidden="true"
            >
              {category.name}
            </span>
            {/* {index === firstCategories.length - 1 &&
              otherCategories.length > 0 && (
                <span
                  className="more-icon"
                  onMouseOver={() => setOpen(true)}
                  onFocus={() => setOpen(true)}
                  aria-hidden={true}
                >{`+${otherCategories.length}`}</span>
              )} */}
          </>
        ))}
      />

      {/* {otherCategories.length > 0 && (
        <div
          className="categories-menu-container"
          onMouseLeave={() => setOpen(false)}
        >
          {open && (
            <ul
              className="categories-menu"
              style={{
                width:
                  parentRef.current?.getBoundingClientRect().width || "auto",
              }}
            >
              <BreadCrumbs
                hyphen=""
                items={otherCategories?.map((category) => (
                  <li
                    className="category-name"
                    onClick={() => categoryClick(category.id)}
                    aria-hidden={true}
                  >
                    {category.name}
                  </li>
                ))}
              />
            </ul>
          )}
        </div>
      )} */}
    </div>
  );
};

export default CategoriesBreadCrumbs;
