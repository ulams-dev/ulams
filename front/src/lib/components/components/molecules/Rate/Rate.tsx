import * as React from "react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import styles from "./Rate.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Button } from "../../atoms/Button/Button";
import { Rating } from "../../atoms/Rating/Rating";
// import { Text } from "../../atoms/Typography/Text";
import { Title } from "../../atoms/Typography/Title";

interface Props extends ExtendableStyledComponent {
  score?: number;
  submitLabel?: string;
  cancelLabel?: string;
  header?: string;
  onSubmit: (rate: number) => void;
  onCancel: () => void;
  children?: React.ReactNode;
}

export const Rate: React.FC<Props> = (props) => {
  const { t } = useTranslation();
  const {
    score,
    header = "Rate.Header",
    submitLabel = "Rate.submitButton",
    cancelLabel = "Rate.cancelButton",
    onSubmit,
    onCancel,
    className = "",
    children,
  } = props;

  const [selectedRate, setSelectedRate] = useState<number>(0);
  const [hoverRate, setHoverRate] = useState<number | undefined>();

  // const selectInfoText = useMemo(() => {
  //   if (hoverRate) {
  //     return t(`Rate.Select${hoverRate}`);
  //   }
  //   if (selectedRate === 0) {
  //     return t("Rate.Select");
  //   }
  //   return t(`Rate.Select${selectedRate}`);
  // }, [selectedRate, hoverRate]);

  return (
    <div className={`ulams-component ${styles.root} ${className}`}>
      <Title className="title" level={4}>
        {t(header)}
      </Title>
      <Rating
        count={score}
        ratingValue={hoverRate ? hoverRate : selectedRate}
        size={"33px"}
        onRateClick={(rate: number) => {
          setHoverRate(undefined);
          setSelectedRate(rate);
        }}
        onIconEnter={setHoverRate}
        onIconLeave={() => {
          setHoverRate(undefined);
        }}
      />
      {/* <Text className="selected-info">{selectInfoText}</Text> */}
      {children}
      <div className="submit-container">
        <Button mode="white" onClick={onCancel}>
          {t(cancelLabel)}
        </Button>
        <Button
          type="button"
          mode="secondary"
          onClick={() => onSubmit(selectedRate)}
          disabled={selectedRate === 0}
        >
          {t(submitLabel)}
        </Button>
      </div>
    </div>
  );
};
