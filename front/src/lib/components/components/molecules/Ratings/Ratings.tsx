import * as React from "react";
import { ReactNode, useCallback } from "react";
import { useTranslation } from "react-i18next";
import { roundPercentageList } from "../../../utils/utils";
import styles from "./Ratings.module.css";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Interval } from "../../atoms/Interval/Interval";
import { Rating } from "../../atoms/Rating/Rating";
import { Text } from "../../atoms/Typography/Text";
import { Title } from "../../atoms/Typography/Title";
interface Rates {
  1: number;
  2: number;
  3: number;
  4: number;
  5: number;
}
interface StyledRatings {
  mobile?: boolean;
}

export interface RatingsProps extends StyledRatings {
  header?: ReactNode;
  sumRates: number;
  avgRate: number;
  rates: Rates;
}

interface RatingsViewProps extends RatingsProps, ExtendableStyledComponent {
  renderRateWithInterval: () => JSX.Element[];
}


const RatingsDesktop: React.FC<RatingsViewProps> = (props) => {
  const { avgRate, header, renderRateWithInterval, className = "" } = props;

  const { t } = useTranslation();
  return (
    <div className={`ulams-component ${styles.desktop} ${className}`}>
      {header && (
        <Title className="header" level={4} as="h1">
          {header}
        </Title>
      )}
      <div className="ratings-container">
        <div className="average-rate-container">
          <Title className="title" level={1} as="h2">
            {avgRate}
          </Title>
          <Rating ratingValue={avgRate} />
          <Text className="average-rate-label">
            {t("Ratings.averageRateLabel")}
          </Text>
        </div>
        <div className="rate-with-interval-container">
          {renderRateWithInterval()}
        </div>
      </div>
    </div>
  );
};


const RatingsMobile: React.FC<RatingsViewProps> = (props) => {
  const { avgRate, header, renderRateWithInterval } = props;

  const { t } = useTranslation();

  return (
    <div className={`ulams-component ${styles.mobile}`}>
      {header && (
        <Title className="header" level={4} as="h2">
          {header}
        </Title>
      )}
      <div className="average-rate-container">
        <Title className="title" level={1}>
          {avgRate}
        </Title>
        <div>
          <Rating ratingValue={avgRate} />
          <Text className="average-rate-label">
            {t("Ratings.averageRateLabel")}
          </Text>
        </div>
      </div>
      <div className="rate-with-interval-container">
        {renderRateWithInterval()}
      </div>
    </div>
  );
};

export const Ratings: React.FC<RatingsProps> = (props) => {
  const { avgRate, rates, sumRates } = props;

  const renderRateWithInterval = useCallback(() => {
    const percentagesValues = Object.keys(rates)
      .sort()
      .map((rateKey: string) => {
        const rate = rates[`${rateKey}` as keyof Rates & string];
        if (rate === 0) {
          return 0;
        }
        return (rate / sumRates) * 100;
      });

    return roundPercentageList(percentagesValues)
      .map((rate: number, index: number) => {
        return (
          <div className="rate-row" key={index}>
            <div className="interval">
              <Interval current={rate} max={100} />
            </div>
            <Rating label={`${rate}%`} ratingValue={index + 1} />
          </div>
        );
      })
      .reverse();
  }, [rates, avgRate]);

  return props.mobile ? (
    <RatingsMobile {...props} renderRateWithInterval={renderRateWithInterval} />
  ) : (
    <RatingsDesktop
      {...props}
      renderRateWithInterval={renderRateWithInterval}
    />
  );
};
