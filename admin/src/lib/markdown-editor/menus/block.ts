import * as React from "react";
import {
  BlockQuoteIcon,
  BulletedListIcon,
  CodeIcon,
  Heading1Icon,
  Heading2Icon,
  Heading3Icon,
  HorizontalRuleIcon,
  MathIcon,
  OrderedListIcon,
  TableIcon,
  TodoListIcon,
  ImageIcon,
  ArchiveIcon,
  HighlightIcon,
  StarredIcon,
  WarningIcon,
  InfoIcon,
  DocumentIcon,
  LinkIcon,
} from "outline-icons";
import { MenuItem } from "../types";
import baseDictionary from "../dictionary";

export default function blockMenuItems(
  dictionary: typeof baseDictionary
): MenuItem[] {
  return [
    {
      name: "image",
      title: "Upload image, audio, video, PDF",
      icon: ImageIcon,
      keywords: "picture photo",
      memberOnly: false,
    },
    {
      name: "sketch",
      title: "Sketch",
      icon: HighlightIcon,
      keywords: "sketch drawing scratchpad",
      memberOnly: true,
    },
    // {
    //   name: "image_occlusion",
    //   title: "Image occlusion",
    //   icon: HighlightIcon,
    //   keywords: "image occlusion",
    //   memberOnly: true,
    // },
    {
      name: "link",
      title: "Link note",
      icon: LinkIcon,
      keywords: "link url uri href",
      shortcut: "[[",
    },
    {
      name: "separator",
    },
    {
      name: "checkbox_list",
      title: dictionary.checkboxList,
      icon: TodoListIcon,
      keywords: "checklist checkbox task",
      shortcut: "[ ]",
    },
    {
      name: "bullet_list",
      title: dictionary.bulletList,
      icon: BulletedListIcon,
      shortcut: "-",
    },
    {
      name: "ordered_list",
      title: dictionary.orderedList,
      icon: OrderedListIcon,
      shortcut: "1.",
    },
    {
      name: "table",
      title: dictionary.table,
      icon: TableIcon,
      attrs: { rowsCount: 3, colsCount: 3 },
    },
    {
      name: "blockquote",
      title: dictionary.quote,
      icon: BlockQuoteIcon,
      shortcut: ">",
    },
    {
      name: "code_block",
      title: dictionary.codeBlock,
      icon: CodeIcon,
      shortcut: "```",
      keywords: "script",
    },
    {
      name: "math_inline",
      title: dictionary.math,
      icon: MathIcon,
      shortcut: "$$",
      keywords: "math latex equation",
    },
    {
      name: "separator",
    },
    {
      name: "heading",
      title: dictionary.h1,
      keywords: "h1 heading1 title",
      icon: Heading1Icon,
      shortcut: "#",
      attrs: { level: 1 },
    },
    {
      name: "heading",
      title: dictionary.h2,
      keywords: "h2 heading2",
      icon: Heading2Icon,
      shortcut: "##",
      attrs: { level: 2 },
    },
    {
      name: "heading",
      title: dictionary.h3,
      keywords: "h3 heading3",
      icon: Heading3Icon,
      shortcut: "###",
      attrs: { level: 3 },
    },
  ];
}
