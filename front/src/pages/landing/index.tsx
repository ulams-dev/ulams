import React, { lazy, useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import { Loader } from "@/components/_App/Loader/Loader";
import { selectLanding } from "./selectLanding";
import { useSettingsReady } from "./shared/useSettingsReady";

const DefaultHome = lazy(() => import("../index"));
const CoffeeLanding = lazy(() => import("./coffee/CoffeeLanding"));
const OncallLanding = lazy(() => import("./oncall/OncallLanding"));
const NightskyLanding = lazy(() => import("./nightsky/NightskyLanding"));

/**
 * Home route (`/`): the tenant's experience landing when its `theme.theme`
 * setting names one of the experience presets, otherwise the default home.
 */
const HomeSwitch: React.FC = () => {
  const { settings } = useContext(UlamsContext);
  const ready = useSettingsReady(settings);
  const choice = selectLanding(settings?.value?.theme?.theme, ready);

  switch (choice) {
    case "coffee":
      return <CoffeeLanding />;
    case "oncall":
      return <OncallLanding />;
    case "nightsky":
      return <NightskyLanding />;
    case "default":
      return <DefaultHome />;
    default:
      return <Loader />;
  }
};

export default HomeSwitch;
