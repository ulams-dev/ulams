import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";

import Layout from "@/components/_App/Layout";
import Container from "@/components/Common/Container";
import PackageProvider from "@/components/Packages/Package/PackageProvider";
import PackageContainer from "@/components/Packages/Package/PackageContainer";

const PackagePage = () => {
  const { product } = useContext(UlamsContext);
  return (
    <Layout metaTitle={product.value?.name}>
      <PackageProvider>
        <Container>
          <PackageContainer />
        </Container>
      </PackageProvider>
    </Layout>
  );
};

export default PackagePage;
