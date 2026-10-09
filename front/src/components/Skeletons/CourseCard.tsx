import Skeleton from "react-loading-skeleton";
import styles from "@/components/Skeletons/Skeletons.module.css";
import { useId } from "react";
import { Col, ScreenClass } from "react-grid-system";

type ColProps = React.ComponentProps<typeof Col>;

type Props = {
  count?: number;
  colProps?: Partial<Record<ScreenClass, ColProps | number | "content">>;
};

export const CourseCardSkeleton: React.FC<Props> = ({
  count = 1,
  colProps,
}) => {
  const id = useId();
  return (
    <>
      {Array.from({ length: count }).map(() =>
        colProps ? (
          // eslint-disable-next-line @typescript-eslint/ban-ts-comment
          // @ts-ignore
          <Col key={`card-skeleton-${id}`} {...colProps}>
            <div className={styles.card}>
              <Skeleton
                height="264px"
                borderRadius={14}
                style={{ marginBottom: "10px" }}
              />
              <Skeleton width={146} style={{ marginBottom: "10px" }} />
              <Skeleton count={2} />
            </div>
          </Col>
        ) : (
          <div className={styles.card} key={`card-skeleton-${id}`}>
            <Skeleton
              height="264px"
              borderRadius={14}
              style={{ marginBottom: "10px" }}
            />
            <Skeleton width={146} style={{ marginBottom: "10px" }} />
            <Skeleton count={2} />
          </div>
        )
      )}
    </>
  );
};
