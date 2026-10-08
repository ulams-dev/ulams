import {
  TrashIcon,
  AlignImageLeftIcon,
  AlignImageRightIcon,
  AlignImageCenterIcon,
  Heading1Icon,
  Heading2Icon,
  Heading3Icon,
} from "outline-icons";
import isNodeActive from "../queries/isNodeActive";
import { MenuItem } from "../types";
import baseDictionary from "../dictionary";
import { EditorState } from "prosemirror-state";

export default function imageMenuItems(
  state: EditorState,
  dictionary: typeof baseDictionary
): MenuItem[] {
  const { schema } = state;
  const isLeftAligned = isNodeActive(schema.nodes.image, {
    layoutClass: "left",
  });
  const isRightAligned = isNodeActive(schema.nodes.image, {
    layoutClass: "right",
  });
  const isCenterAligned = isNodeActive(schema.nodes.image, {
    layoutClass: "center",
  });
  const isSmall = isNodeActive(schema.nodes.image, {
    sizeClass: "small",
  });
  const isMedium = isNodeActive(schema.nodes.image, {
    sizeClass: "medium",
  });
  const isLarge = isNodeActive(schema.nodes.image, {
    sizeClass: "large",
  });

  return [
    {
      name: "alignLeft",
      tooltip: dictionary.alignLeft,
      icon: AlignImageLeftIcon,
      visible: true,
      active: isLeftAligned,
    },
    {
      name: "alignCenter",
      tooltip: dictionary.alignCenter,
      icon: AlignImageCenterIcon,
      visible: true,
      active: (state) =>
        isNodeActive(schema.nodes.image)(state) && isCenterAligned(state),
    },
    {
      name: "alignRight",
      tooltip: dictionary.alignRight,
      icon: AlignImageRightIcon,
      visible: true,
      active: isRightAligned,
    },
    {
      name: "separator",
      visible: true,
    },
    {
      name: "smallSize",
      tooltip: dictionary.smallImage,
      icon: Heading1Icon,
      visible: true,
      active: isSmall,
    },
    {
      name: "mediumSize",
      tooltip: dictionary.mediumImage,
      icon: Heading2Icon,
      visible: true,
      active: isMedium,
    },
    {
      name: "largeSize",
      tooltip: dictionary.largeImage,
      icon: Heading3Icon,
      visible: true,
      active: isLarge,
    },
    {
      name: "separator",
      visible: true,
    },
    {
      name: "deleteImage",
      tooltip: dictionary.deleteImage,
      icon: TrashIcon,
      visible: true,
      active: () => false,
    },
  ];
}
