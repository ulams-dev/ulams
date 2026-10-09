import { FC, HTMLAttributes, ReactNode } from "react";
import { IconText, Text } from "../../..";
import { useThemeTokens } from "../../../theme/applyTheme";
import styles from "./List.module.css";
export interface ListItemProps {
  id: number;
  text: string;
  icon: ReactNode;
  numberOfItems: number;
}

interface ListProps extends Omit<HTMLAttributes<HTMLUListElement>, "onClick"> {
  listItems: ListItemProps[];
  selectedListItem: number;
  setSelectedListItem: (i: number) => void;
  defaultSelectedId?: number;
  currentIndex?: number;
}

const List: FC<ListProps> = ({
  listItems,
  selectedListItem,
  setSelectedListItem,
  className,
  ...props
}) => {
  const tokens = useThemeTokens();
  const hasOutlineColor = Boolean(
    tokens?.outlineButtonColor || tokens?.dm__outlineButtonColor
  );

  return (
    <ul
      data-testid="list"
      className={[
        styles.list,
        hasOutlineColor ? styles.hasOutlineColor : "",
        className ?? "",
      ]
        .filter(Boolean)
        .join(" ")}
      {...props}
    >
      {listItems.map(({ id, icon, text, numberOfItems }) => {
        const isActive = selectedListItem === id;
        return (
          <li
            key={id}
            className={`${styles.item} ${isActive ? styles.active : ""}`}
            data-testid={text}
            onClick={() => setSelectedListItem(id)}
          >
            <IconText
              className={styles.iconText}
              icon={icon}
              text={text}
              noMargin
            />
            <Text className={styles.text}>{numberOfItems}</Text>
          </li>
        );
      })}
    </ul>
  );
};

export default List;
