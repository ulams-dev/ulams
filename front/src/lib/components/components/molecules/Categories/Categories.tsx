import * as React from "react";
import type { Category } from "@ulams/sdk/types";
import { ReactNode, useRef } from "react";
import { useOnClickOutside } from "../../../hooks/useOnClickOutside";
import { contrast } from "chroma-js";
import { Title, Checkbox, Button } from "../../../";
import Drawer from "rc-drawer";
import { useTranslation } from "react-i18next";
import { useThemeTokens } from "../../../theme/applyTheme";
import styles from "./Categories.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

interface StyledCategoriesProps {
  mobile?: boolean;
  open?: boolean;
  lightContrast?: boolean;
  backgroundColor?: React.CSSProperties["backgroundColor"];
}

interface CategoriesProps
  extends StyledCategoriesProps,
    ExtendableStyledComponent {
  categories: Category[];
  label?: string;
  labelPrefix?: string;
  selectedCategories?: number[];
  handleChange?: (newValue: number[]) => void;
  drawerTitle?: ReactNode;
  handleDrawerButtonClick?: () => void;
  drawerButtonText?: string;
}

const IconArrowBottom = () => {
  return (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M6 9L12 15L18 9"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
};

const IconArrowLeft = () => {
  return (
    <svg
      width="8"
      height="14"
      viewBox="0 0 8 14"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M7 1L1 7L7 13"
        stroke="#4A4A4A"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
};

const CategoryTreeOptions: React.FC<CategoriesProps> = (props) => {
  const {
    categories,
    labelPrefix,
    selectedCategories = [],
    label,
    handleChange,
    mobile,
    className = "",
  } = props;

  const [collapseState, setCollapseState] = React.useState<{
    [key: number]: boolean;
  }>({});

  const handleCollapse = (id: number) => {
    setCollapseState({
      ...collapseState,
      [id]: !collapseState[id],
    });
  };

  const onInternalChange = React.useCallback(
    (id: number) => {
      if (handleChange) {
        handleChange(
          selectedCategories.includes(id)
            ? selectedCategories.filter((pid) => pid !== id)
            : [...selectedCategories, id]
        );
      }
    },
    [selectedCategories]
  );

  return (
    <div
      className={`ulams-component ${styles.treeOptions} ${
        mobile ? "categories-drawer-list" : "categories-dropdown-options"
      } ${className}`}
    >
      {mobile && label && (
        <Title
          level={5}
          style={{
            marginTop: "32px",
            marginBottom: "17px",
          }}
        >
          {label}
        </Title>
      )}
      {categories.map((category: Category) => (
        <div key={category.id}>
          <Checkbox
            value={category.id}
            label={
              labelPrefix ? `${labelPrefix}${category.name}` : category.name
            }
            checked={selectedCategories.includes(category.id)}
            onChange={() => onInternalChange(category.id)}
          />

          {category &&
            category.subcategories &&
            category.subcategories.length > 0 && (
              <React.Fragment>
                <button
                  type={"button"}
                  onClick={() => handleCollapse(category.id)}
                  className="categories-collapse"
                >
                  <IconArrowBottom />
                </button>
                {collapseState[category.id] && (
                  <CategoryTreeOptions
                    categories={category.subcategories}
                    handleChange={handleChange}
                    selectedCategories={selectedCategories}
                    mobile={mobile}
                  />
                )}
              </React.Fragment>
            )}
        </div>
      ))}
    </div>
  );
};

const CategoriesDropdown: React.FC<CategoriesProps> = (props) => {
  const theme = useThemeTokens();

  const {
    categories,
    labelPrefix,
    label,
    selectedCategories,
    handleChange,
    backgroundColor,
  } = props;

  // The contrast check needs a raw colour; the default background comes from the theme.
  const rawBackground =
    backgroundColor ??
    (theme?.mode === "dark"
      ? theme?.dm__background ?? theme?.background
      : theme?.background) ??
    "#FFFFFF";

  const cts = React.useMemo(() => {
    try {
      return contrast("#fff", rawBackground) >= 1.85;
    } catch {
      return false;
    }
  }, [rawBackground]);

  const [open, setOpen] = React.useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const toggleOpen = () => {
    setOpen((open) => !open);
  };

  useOnClickOutside(ref, () => setOpen(false));

  return (
    <div
      ref={ref}
      className={`ulams-component ${styles.dropdown} ${
        open ? styles.open : ""
      } ${cts ? styles.lightContrast : ""}`}
      style={
        {
          "--categories-bg": backgroundColor ?? "var(--ulams-color-bg)",
        } as React.CSSProperties
      }
    >
      <button
        type={`button`}
        className={"categories-dropdown-button"}
        onClick={toggleOpen}
      >
        {label}{" "}
        {selectedCategories &&
          selectedCategories.length > 0 &&
          `(${selectedCategories.length})`}
        <IconArrowBottom />
      </button>
      <CategoryTreeOptions
        categories={categories}
        labelPrefix={labelPrefix}
        selectedCategories={selectedCategories}
        handleChange={handleChange}
      />
    </div>
  );
};

const CategoriesDrawer: React.FC<CategoriesProps> = (props) => {
  const {
    categories,
    labelPrefix,
    label,
    handleChange,
    handleDrawerButtonClick,
    selectedCategories,
    drawerButtonText,
    drawerTitle,
    mobile,
  } = props;
  const [showDrawer, setShowDrawer] = React.useState(false);
  const { t } = useTranslation();
  const onToggleDrawer = () => {
    setShowDrawer((value) => !value);
  };

  return (
    <React.Fragment>
      <span className={styles.drawerMounted} hidden />
      <Button type={"button"} mode={"outline"} onClick={onToggleDrawer}>
        {t("Categories.Filter")}{" "}
        {selectedCategories &&
          selectedCategories.length > 0 &&
          `(${selectedCategories.length})`}
      </Button>
      <Drawer open={showDrawer} onClose={onToggleDrawer}>
        <div className={"drawer-content-header"}>
          <button
            type={"button"}
            onClick={onToggleDrawer}
            className={"drawer-content-btn"}
          >
            <IconArrowLeft />
          </button>
          {drawerTitle && <React.Fragment>{drawerTitle}</React.Fragment>}
        </div>
        <div className={"drawer-content-inner"}>
          <CategoryTreeOptions
            categories={categories}
            label={label}
            labelPrefix={labelPrefix}
            selectedCategories={selectedCategories}
            handleChange={handleChange}
            mobile={mobile}
          />
        </div>
        {drawerButtonText && handleDrawerButtonClick && (
          <div className={"drawer-content-footer"}>
            <Button
              block
              mode={"secondary"}
              onClick={() => {
                onToggleDrawer();
                handleDrawerButtonClick && handleDrawerButtonClick();
              }}
            >
              {drawerButtonText && drawerButtonText}
            </Button>
          </div>
        )}
      </Drawer>
    </React.Fragment>
  );
};

export const Categories: React.FC<CategoriesProps> = (props) => {
  const { mobile } = props;

  return (
    <React.Fragment>
      {mobile ? (
        <CategoriesDrawer {...props} />
      ) : (
        <React.Fragment>
          <CategoriesDropdown {...props} />
        </React.Fragment>
      )}
    </React.Fragment>
  );
};

export default Categories;
