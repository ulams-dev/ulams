import React from "react";
import { ICONS_DICTIONARY } from "./_components/IconsDictionary";
import { legacyDefault } from "../../../utils/legacy";

type IconName = keyof typeof ICONS_DICTIONARY;

interface Props extends Omit<React.HTMLAttributes<HTMLPictureElement>, "name"> {
  name: IconName;
}

export const Icon: React.FC<Props> = ({ name, ...pictureProps }) => {
  const icon: React.FC | undefined = ICONS_DICTIONARY?.[name];

  return (
    <picture {...pictureProps}>
      {icon ? React.createElement(icon) : <>Icon {name} missing</>}
    </picture>
  );
};

export default legacyDefault(Icon);
