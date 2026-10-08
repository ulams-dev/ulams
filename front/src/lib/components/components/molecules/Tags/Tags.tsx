import * as React from "react";
import type { Tag } from "@ulams/sdk/types";
import { ReactNode, useRef } from "react";
import { useOnClickOutside } from "../../../hooks/useOnClickOutside";
import { contrast } from "chroma-js";
import { Title, Checkbox, Button } from "../../../";
import Drawer from "rc-drawer";
import { useTranslation } from "react-i18next";
import { useThemeTokens } from "../../../theme/applyTheme";
import styles from "./Tags.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

interface StyledTagsProps {
  mobile?: boolean;
  open?: boolean;
  lightContrast?: boolean;
  backgroundColor?: React.CSSProperties["backgroundColor"];
}

interface TagsProps extends StyledTagsProps, ExtendableStyledComponent {
  tags: Tag[];
  label?: string;
  labelPrefix?: string;
  selectedTags?: string[];
  handleChange?: (newValue: string[]) => void;
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

const TagsTreeOptions: React.FC<TagsProps> = (props) => {
  const {
    tags,
    labelPrefix,
    selectedTags = [],
    label,
    handleChange,
    mobile,
    className = "",
  } = props;

  const onInternalChange = React.useCallback(
    (tagName: string) => {
      if (handleChange) {
        handleChange(
          selectedTags.includes(tagName)
            ? selectedTags.filter((tag) => tag !== tagName)
            : [...selectedTags, tagName]
        );
      }
    },
    [selectedTags]
  );

  return (
    <div
      className={`ulams-component ${styles.treeOptions} ${
        mobile ? "tags-drawer-list" : "tags-dropdown-options"
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
      {tags.map((tag: Tag) => (
        <div key={tag.id}>
          <Checkbox
            value={tag.id}
            label={labelPrefix ? `${labelPrefix}${tag.title}` : tag.title}
            checked={selectedTags.includes(tag.title)}
            onChange={() => onInternalChange(tag.title)}
          />
        </div>
      ))}
    </div>
  );
};

const TagsDropdown: React.FC<TagsProps> = (props) => {
  const theme = useThemeTokens();

  const { tags, labelPrefix, label, selectedTags, handleChange, backgroundColor } =
    props;

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
          "--tags-bg": backgroundColor ?? "var(--ulams-color-bg)",
        } as React.CSSProperties
      }
    >
      <button
        type={`button`}
        className={"tags-dropdown-button"}
        onClick={toggleOpen}
      >
        {label}{" "}
        {selectedTags && selectedTags.length > 0 && `(${selectedTags.length})`}
        <IconArrowBottom />
      </button>
      <TagsTreeOptions
        tags={tags}
        labelPrefix={labelPrefix}
        selectedTags={selectedTags}
        handleChange={handleChange}
      />
    </div>
  );
};

const TagsDrawer: React.FC<TagsProps> = (props) => {
  const {
    tags,
    labelPrefix,
    label,
    handleChange,
    handleDrawerButtonClick,
    selectedTags,
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
        {t("Tags.Filter")}{" "}
        {selectedTags && selectedTags.length > 0 && `(${selectedTags.length})`}
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
          <TagsTreeOptions
            tags={tags}
            label={label}
            labelPrefix={labelPrefix}
            selectedTags={selectedTags}
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

export const Tags: React.FC<TagsProps> = (props) => {
  const { mobile } = props;

  return (
    <React.Fragment>
      {mobile ? (
        <TagsDrawer {...props} />
      ) : (
        <React.Fragment>
          <TagsDropdown {...props} />
        </React.Fragment>
      )}
    </React.Fragment>
  );
};

export default Tags;
