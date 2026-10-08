import React, { useContext, useEffect } from "react";
import { useParams } from "react-router-dom";

import { UlamsContext } from "@ulams/sdk/react/context";

export const PackageContext: React.Context<{}> = React.createContext({});

const PackageProvider: React.FC<{
  children: React.ReactNode;
}> = ({ children }) => {
  const { id } = useParams<{ id: string }>();
  const { fetchProduct } = useContext(UlamsContext);

  useEffect(() => {
    if (id) {
      fetchProduct(Number(id));
    }
  }, [fetchProduct, id]);

  return (
    <PackageContext.Provider value={{}}>{children}</PackageContext.Provider>
  );
};

export default PackageProvider;
