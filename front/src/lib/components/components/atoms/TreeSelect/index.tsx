import { useRef } from "react";
import ReactTreeSelect, {
  TreeSelectProps as ReactTreeSelectProps,
} from "rc-tree-select";
import { Icon } from "../../../";
import styles from "./TreeSelect.module.css";

export type TreeSelectProps<ValueType> = Omit<
  ReactTreeSelectProps<ValueType>,
  "switcherIcon" | "inputIcon" | "treeNodeLabelProp" | "getPopupContainer"
>;

export const TreeSelect = <ValueType,>({
  placeholder = "Provide data",
  ...props
}: TreeSelectProps<ValueType>) => {
  const wrapperRef = useRef<HTMLDivElement | null>(null);

  return (
    <div ref={wrapperRef} className={styles.wrapper}>
      <ReactTreeSelect
        {...props}
        placeholder={placeholder}
        switcherIcon={
          <Icon name="chevronLeft" styles={{ with: 5, height: 10 }} />
        }
        treeNodeLabelProp="label"
        getPopupContainer={() => wrapperRef.current as HTMLDivElement}
      />
    </div>
  );
};
