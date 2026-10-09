import { Link } from "react-router-dom";
import { Col } from "react-grid-system";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Tag } from "@ulams/components/components/atoms/Tag/Tag";
import { API } from "@ulams/sdk";
import styles from "./Tag.module.css";

interface Props {
  products: API.ProductItems[];
  linkTo: string;
}

export const PackageSidebarTag = ({ products, linkTo }: Props) => (
  <Col lg={12} className={styles.col}>
    {products.map((product) => (
      <Link to={`${linkTo}/${product.productable_id}`}>
        <Tag>
          <Text size={"12"}>{product.name}</Text>
        </Tag>
      </Link>
    ))}
  </Col>
);
