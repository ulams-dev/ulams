import React, { FC, useRef, useState, useCallback, cloneElement } from "react";
import { CSSTransition } from "react-transition-group";
import { useOnClickOutside } from "../../../hooks/useOnClickOutside";
import { Checkbox } from "../../../";
import { Text } from "../../../";
import { Category } from "@ulams/sdk/types";
import styles from "./DropdownCategories.module.css";

interface Props {
  categories: Category[];
  checkedCategories: Category[];
  isInitiallyOpen?: boolean;
  onChange: (category: Category) => void;
  onClick?: () => void;
  onClear?: () => void;
  child?: React.ReactElement<{ onClick?: () => void; $isMenuOpen?: boolean }>;
  forMobile?: boolean;
}

const DropdownCategoriesRecursive: FC<
  Pick<Props, "categories" | "checkedCategories" | "onChange">
> = ({ categories, checkedCategories, onChange }: Props) => {
  const handleCategoryClick = (category: Category) => {
    onChange(category);
  };

  return (
    <>
      {categories.map((category: Category) => (
        <li className={styles.item} key={category.id}>
          <Checkbox
            name={category.name}
            label={category.name}
            checked={
              checkedCategories.find((item) => item.id === category.id)
                ? true
                : false
            }
            onChange={() => handleCategoryClick(category)}
          />

          {category.subcategories && category.subcategories.length > 0 && (
            <div className="subcategories">
              <DropdownCategoriesRecursive
                checkedCategories={checkedCategories}
                categories={category.subcategories}
                onChange={onChange}
              />
            </div>
          )}
        </li>
      ))}
    </>
  );
};

export const DropdownCategories: React.FC<Props> = ({
  child,
  categories,
  checkedCategories,
  isInitiallyOpen,
  onClick,
  onChange,
  onClear,
  forMobile,
}) => {
  const dropdownMenuRef = useRef<HTMLUListElement | null>(null);
  const [isOpen, setIsOpen] = useState(isInitiallyOpen);
  const closeMenu = () => setIsOpen(false);
  useOnClickOutside(dropdownMenuRef, () => closeMenu());

  const handleCategoryClick = useCallback(
    (category: Category) => {
      onChange?.(category);
    },
    [onChange]
  );

  const handleClear = () => {
    onClear?.();
    closeMenu();
  };

  return (
    <div
      className={`${styles.wrapper} ${forMobile ? styles.forMobile : ""}`}
      onClick={onClick}
    >
      {!!child &&
        cloneElement(child as React.ReactElement, {
          onClick: () => setIsOpen((prev) => !prev),
          $isMenuOpen: isOpen,
        })}
      <CSSTransition
        in={isOpen}
        timeout={300}
        nodeRef={dropdownMenuRef}
        classNames="fade"
        unmountOnExit
      >
        <ul
          ref={dropdownMenuRef}
          className={`${styles.menu} ${forMobile ? styles.forMobile : ""}`}
        >
          <li className={styles.clearItem}>
            <Text size="16">Wybierz</Text>
            <button onClick={handleClear}>
              <Text size="13">Wyczyść</Text>
            </button>
          </li>
          <DropdownCategoriesRecursive
            checkedCategories={checkedCategories}
            categories={categories}
            onChange={handleCategoryClick}
          />
        </ul>
      </CSSTransition>
    </div>
  );
};

export default DropdownCategories;
