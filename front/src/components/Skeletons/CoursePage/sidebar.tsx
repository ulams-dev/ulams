import Skeleton from "react-loading-skeleton";
import styles from "@/components/Skeletons/Skeletons.module.css";

const SidebarSkeleton = () => {
  return (
    <div className={styles.sidebar}>
      <Skeleton width={"100%"} height={350} />
    </div>
  );
};

export default SidebarSkeleton;
