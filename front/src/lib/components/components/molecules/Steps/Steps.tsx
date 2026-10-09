import * as React from "react";

import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Radio } from "../../atoms/Option/Radio";
import styles from "./Steps.module.css";

export interface StepsOptionProps extends ExtendableStyledComponent {
  value: string;
  label: string;
  checked?: boolean;
  onChange: (value: string) => void;
}

export interface StepsProps
  extends React.HTMLAttributes<HTMLDivElement>,
    ExtendableStyledComponent {
  options: StepsOptionProps[];
  checked: number;
}

const StepsOption: React.FC<StepsOptionProps> = (props) => {
  const { value, label, checked, className = "" } = props;

  return (
    <div className={`ulams-component ${styles.option} ${className}`}>
      <Radio
        value={value}
        checked={checked}
        label={label}
        onChange={() => props.onChange(value)}
      />
    </div>
  );
};

export const Steps: React.FC<StepsProps> = (props) => {
  const { options, checked, className = "" } = props;
  const [checkedOption, setCheckedOption] = React.useState(checked || 0);

  const progressBarWidth = `${Math.round(
    ((checkedOption + 1) / options.length) * 100
  )}%`;

  return (
    <div className={`ulams-component ${styles.steps} ${className}`}>
      <div className={"progress-bar"} style={{ width: progressBarWidth }} />
      {options.map((option, index) => (
        <StepsOption
          key={option.value}
          value={option.value}
          label={option.label}
          checked={index === checkedOption}
          onChange={() => setCheckedOption(index)}
        />
      ))}
    </div>
  );
};

export default Steps;
