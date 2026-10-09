import React from "react";
import Drawer from "rc-drawer";
import "rc-drawer/assets/index.css";
import styles from "./MobileDrawer.module.css";

type Props = {
  children: React.ReactNode;
  isOpen: boolean;
  onClose: () => void;
  height?: string;
};

const MobileDrawer: React.FC<Props> = ({
  children,
  isOpen,
  onClose,
  height,
}) => {
  return (
    <div className={styles.mobileDrawer}>
      <Drawer
        open={isOpen}
        rootStyle={
          height
            ? ({ "--mobile-drawer-height": height } as React.CSSProperties)
            : undefined
        } // @ts-ignore
        classNames={{
          wrapper: "mobile-drawer-drawer-wrapper",
          content: "drawer-content",
        }}
        onClose={onClose}
        placement="bottom"
      >
        {children}
      </Drawer>
    </div>
  );
};
export default MobileDrawer;
