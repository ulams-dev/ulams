/* eslint-disable jsx-a11y/no-noninteractive-element-interactions */
import React, { FC, useRef, useState, cloneElement, useCallback } from "react";
import { CSSTransition } from "react-transition-group";
import { useOnClickOutside } from "../../../hooks/useOnClickOutside";

import { Text } from "../../../";
import styles from "./DropdownMenu.module.css";

export interface DropdownMenuItem {
  id: number | string;
  content: React.ReactNode;
  redirect?: string;
}

interface Props {
  child: React.ReactElement<{ onClick?: () => void; $isMenuOpen?: boolean }>;
  menuItems: DropdownMenuItem[];
  isInitiallyOpen?: boolean;
  onClick?: () => void;
  onChange?: (listItem: DropdownMenuItem) => void;
  top?: number;
}

const DropdownMenu: FC<Props> = ({
  child,
  menuItems,
  isInitiallyOpen,
  onClick,
  onChange,
  top,
}) => {
  const dropdownMenuRef = useRef<HTMLUListElement | null>(null);
  const [isOpen, setIsOpen] = useState(isInitiallyOpen);
  const closeMenu = () => setIsOpen(false);
  useOnClickOutside(dropdownMenuRef, () => closeMenu());

  const onListItemClick = useCallback(
    (ind: number) => {
      onChange?.(menuItems[ind]);
      closeMenu();
    },
    [menuItems, onChange]
  );

  return (
    <div className={styles.wrapper} onClick={onClick}>
      {cloneElement(child as React.ReactElement, {
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
          className={styles.menu}
          style={
            top
              ? ({ "--dropdown-menu-top": `${top}px` } as React.CSSProperties)
              : undefined
          }
        >
          {menuItems.map(({ id, content }, index) => (
            <li
              className={styles.item}
              key={id}
              onClick={() => onListItemClick(index)}
              onKeyDown={closeMenu}
            >
              <Text size="14" noMargin>
                {content}
              </Text>
            </li>
          ))}
        </ul>
      </CSSTransition>
    </div>
  );
};

export default DropdownMenu;
