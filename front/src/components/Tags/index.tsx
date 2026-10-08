import { useCallback, useRef, useState } from "react";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import styles from "./Tags.module.css";
import { Badge } from "@ulams/components/components/atoms/Badge/Badge";
import { API } from "@ulams/sdk";

export interface TagsProps {
  tags: string[] | API.Tag[] | null | undefined;
  onTagClick?: (title: string) => void;
}

const Tags = (props: TagsProps) => {
  const { tags, onTagClick } = props;
  const theme = useThemeTokens();
  const [open, setOpen] = useState(false);
  const firstTags = tags ? [...tags].splice(0, 2) : [];
  const otherTags = tags ? [...tags].splice(2) : [];
  const parentRef = useRef<HTMLDivElement | null>(null);

  const tagClick = useCallback(
    (e: React.MouseEvent<HTMLDivElement, MouseEvent>, title: string) => {
      if (onTagClick) {
        onTagClick(title);
      }
    },
    [onTagClick]
  );

  return (
    <div className={styles.root} ref={parentRef}>
      {firstTags.map((tag, index) => {
        const tagTitle = (tag as API.Tag).title ?? tag;
        return (
          <Badge
            className={styles.badge}
            color={theme?.primaryColor}
            key={index}
            onClick={(e: React.MouseEvent<HTMLDivElement, MouseEvent>) =>
              tagClick(e, tagTitle)
            }
          >
            {tagTitle}
          </Badge>
        );
      })}
      {otherTags.length > 0 && (
        <div
          className={styles.menuContainer}
          onMouseLeave={() => setOpen(false)}
        >
          <Badge
            className={styles.badge}
            color={theme?.primaryColor}
            onMouseOver={() => setOpen(true)}
          >
            {`+${otherTags.length}`}
          </Badge>
          {open && (
            <ul className={styles.menu}>
              {otherTags.map((otherTag, index) => {
                const otherTagTitle = (otherTag as API.Tag).title ?? otherTag;
                return (
                  <li>
                    <Badge
                      key={index}
                      className={styles.badge}
                      onClick={(
                        e: React.MouseEvent<HTMLDivElement, MouseEvent>
                      ) => tagClick(e, otherTagTitle)}
                      color={theme?.primaryColor}
                    >
                      {otherTagTitle}
                    </Badge>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      )}
    </div>
  );
};

export default Tags;
