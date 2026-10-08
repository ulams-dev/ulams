import { useContext, useMemo } from "react";
import { Row } from "react-grid-system";
import styles from "./Tags.module.css";
import { UlamsContext } from "@ulams/sdk/react";
import { groupProductablesByType, ProductableEnum } from "@/utils/productables";
import { PackageSidebarTag } from "./Tag";

export const PackageSidebarTags = () => {
  const { product } = useContext(UlamsContext);
  const grouped = useMemo(
    () => groupProductablesByType(product?.value?.productables || []),
    [product?.value?.productables]
  );

  return (
    <Row className={styles.row}>
      {grouped[ProductableEnum.Consultation] && (
        <PackageSidebarTag
          linkTo="/consultations"
          products={grouped[ProductableEnum.Consultation]}
        />
      )}
      {grouped[ProductableEnum.Webinar] && (
        <PackageSidebarTag
          linkTo="/webinar"
          products={grouped[ProductableEnum.Webinar]}
        />
      )}
      {grouped[ProductableEnum.StationaryEvent] && (
        <PackageSidebarTag
          linkTo="/event"
          products={grouped[ProductableEnum.StationaryEvent]}
        />
      )}
      {grouped[ProductableEnum.Course] && (
        <PackageSidebarTag
          linkTo="/courses"
          products={grouped[ProductableEnum.Course]}
        />
      )}
    </Row>
  );
};
