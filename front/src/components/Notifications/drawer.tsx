import Drawer from "rc-drawer";
import { isMobile } from "react-device-detect";
import Notifications from "@/components/Notifications";
import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import "./drawer.module.css";

type Props = {
  isOpen: boolean;
  onClose: () => void;
};

const NotificationsDrawer: React.FC<Props> = ({ isOpen, onClose }) => {
  const { fetchNotifications } = useContext(UlamsContext);

  const handleClose = () => {
    onClose();
    fetchNotifications();
  };

  return (
    <Drawer
      open={isOpen}
      onClose={handleClose}
      width={isMobile ? "100%" : "500px"}
      placement="right"
      className="notifications-drawer"
      // @ts-ignore
      classNames={{
        wrapper: "notifications-drawer",
        content: "notifications-drawer__content",
      }}
    >
      <Notifications onClose={handleClose} />
    </Drawer>
  );
};

export default NotificationsDrawer;
