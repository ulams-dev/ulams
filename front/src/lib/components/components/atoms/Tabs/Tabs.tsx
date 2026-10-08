import * as React from "react";
import { ReactNode } from "react";
import { getUniqueId } from "../../../utils/utils";
import { cx } from "../../../utils/cx";
import styles from "./Tabs.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { legacyDefault } from "../../../utils/legacy";

interface TabProps {
  label: string;
  key: number;
  component: ReactNode;
  hidden?: boolean;
}

export interface TabsProps extends ExtendableStyledComponent {
  tabs: TabProps[];
  defaultActiveKey: number;
  onClick?: (key: number) => void;
}

export const Tabs: React.FC<TabsProps> = (props) => {
  const {
    tabs = [],
    defaultActiveKey = tabs[0].key,
    onClick,
    className = "",
  } = props;
  const [selectedTab, setSelectedTab] =
    React.useState<number>(defaultActiveKey);
  const panel = tabs && tabs.find((tab) => tab.key === selectedTab);

  return (
    <div className={cx(styles.tabs, "ulams-component", className)}>
      <div className={"tabs-menu"}>
        <div className={"tabs-menu-inner"}>
          {tabs.map((tab) => {
            if (tab.hidden) {
              return null;
            }

            return (
              <button
                type={"button"}
                className={`tab-menu-btn ${
                  selectedTab === tab.key ? "active" : ""
                }`}
                key={tab.key}
                id={getUniqueId(`tab-menu-${tab.key}`)}
                onClick={() => {
                  setSelectedTab(tab.key);
                  onClick && onClick(tab.key);
                }}
              >
                {tab.label}
              </button>
            );
          })}
        </div>
      </div>
      <div id={`tabpanel-${selectedTab}`} className={"tabs-panel"}>
        <React.Fragment>{panel && panel.component}</React.Fragment>
      </div>
    </div>
  );
};

export default legacyDefault(Tabs);
