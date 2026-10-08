import * as React from "react";
import { ReactNode, useRef } from "react";
import { Input, Spin } from "../../../";
import { Text } from "../../../";
import { useOnClickOutside } from "../../../hooks/useOnClickOutside";
import { useTranslation } from "react-i18next";
import styles from "./Search.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

interface StyledSearchProps {
  isFocused?: boolean;
  loading?: boolean;
}

export interface SearchProps
  extends StyledSearchProps,
    ExtendableStyledComponent {
  onSearch?: (value: string) => void;
  onChange?: (value: string) => void;
  onSubmit?: (value: string) => void;
  filterOptions?: () => void;
  placeholder: string;
  children?: ReactNode;
  icon?: ReactNode;
}

const IconSearch = () => {
  return (
    <svg
      width="20"
      height="21"
      viewBox="0 0 20 21"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M8 2.82715C4.68629 2.82715 2 5.51344 2 8.82715C2 12.1409 4.68629 14.8271 8 14.8271C11.3137 14.8271 14 12.1409 14 8.82715C14 5.51344 11.3137 2.82715 8 2.82715ZM0 8.82715C0 4.40887 3.58172 0.827148 8 0.827148C12.4183 0.827148 16 4.40887 16 8.82715C16 10.6759 15.3729 12.3781 14.3199 13.7328L19.7071 19.12C20.0976 19.5106 20.0976 20.1437 19.7071 20.5343C19.3166 20.9248 18.6834 20.9248 18.2929 20.5343L12.9056 15.147C11.551 16.2001 9.84871 16.8271 8 16.8271C3.58172 16.8271 0 13.2454 0 8.82715Z"
        fill="#4A4A4A"
      />
    </svg>
  );
};

export const Search: React.FC<SearchProps> = (props) => {
  const {
    children,
    placeholder,
    icon,
    loading,
    onSearch,
    onChange,
    onSubmit,
    filterOptions,
    className = "",
  } = props;
  const [value, setValue] = React.useState("");
  const [isFocused, setIsFocused] = React.useState(false);
  const childrenList = React.Children.toArray(children);
  const ref = useRef(null);
  const { t } = useTranslation();

  useOnClickOutside(ref, () => setIsFocused(false));

  const toggleFocus = () => {
    setIsFocused((isFocused) => !isFocused);
  };

  const filterChildren = (childrenList: ReactNode[]) => {
    return childrenList
      .filter((child) => {
        if (React.isValidElement(child)) {
          if (typeof filterOptions === "function") {
            return filterOptions();
          } else {
            return child.props.children
              .toLowerCase()
              .includes(value.toLowerCase());
          }
        }
        return false;
      })
      .map((child) => {
        if (React.isValidElement(child)) {
          return React.cloneElement(child, {
            // TODO fix this, but how ?
            // eslint-disable-next-line @typescript-eslint/ban-ts-comment
            // @ts-ignore
            onClick: (e: Event) => {
              onChange && onChange(child.props.children);
              toggleFocus();
              setValue(child.props.children);
              if (child.props.onClick) {
                child.props.onClick(e);
              }
            },
          });
        }
        return child;
      });
  };

  return (
    <div
      className={`ulams-component ${styles.root} ${className}`}
      ref={ref}
      data-focused={isFocused ? "true" : "false"}
      data-loading={loading ? "true" : "false"}
    >
      <div className="search-input-wrapper">
        <Input
          placeholder={placeholder}
          onChange={(e: { target: { value: string } }) => {
            const { value } = e.target;
            setValue(value);
            onSearch && onSearch(value);
          }}
          value={value}
          onKeyDown={(e: { key: string }) =>
            e.key === "Enter" && onSubmit && onSubmit(value)
          }
          onFocus={() => toggleFocus()}
        />

        <button
          type={"button"}
          className={"search-input-button"}
          onClick={() => {
            onSubmit && onSubmit(value);
          }}
          aria-label={t<string>("Search.Search")}
        >
          {loading ? <Spin color={"currentColor"} /> : icon || <IconSearch />}
        </button>
      </div>
      <div className={"search-input-options"}>
        {filterChildren(childrenList).length ? (
          filterChildren(childrenList)
        ) : (
          <Text size={"12"}>{t<string>("Search.NoResults")}</Text>
        )}
      </div>
    </div>
  );
};

export default Search;
