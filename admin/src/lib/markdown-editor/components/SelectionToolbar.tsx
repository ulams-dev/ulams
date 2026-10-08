import assert from "assert";
import * as React from "react";
import { Portal } from "react-portal";
import { some } from "lodash";
import { EditorView } from "prosemirror-view";
import getTableColMenuItems from "../menus/tableCol";
import getTableRowMenuItems from "../menus/tableRow";
import getTableMenuItems from "../menus/table";
import getFormattingMenuItems from "../menus/formatting";
import getImageMenuItems from "../menus/image";
import FloatingToolbar from "./FloatingToolbar";
import LinkEditor from "./LinkEditor";
import Menu from "./Menu";
import isMarkActive from "../queries/isMarkActive";
import getMarkRange from "../queries/getMarkRange";
import isNodeActive from "../queries/isNodeActive";
import getColumnIndex from "../queries/getColumnIndex";
import getRowIndex from "../queries/getRowIndex";
import { MenuItem } from "../types";
import baseDictionary from "../dictionary";

export const getText = content => {
  if (!content) {
    return "";
  } else if (content.text) {
    return content.text;
  } else if (Array.isArray(content)) {
    return content.map(getText).join("");
  } else if (typeof content === "object" && content !== null) {
    return getText(content.content);
  }
};

export const iOS = () => {
  return (
    [
      "iPad Simulator",
      "iPhone Simulator",
      "iPod Simulator",
      "iPad",
      "iPhone",
      "iPod",
    ].includes(navigator.platform) ||
    // iPad on iOS 13 detection
    (navigator.userAgent.includes("Mac") && "ontouchend" in document)
  );
};

export const android = () => {
  const ua = navigator.userAgent.toLowerCase();
  return ua.indexOf("android") > -1;
};

type Props = {
  dictionary: typeof baseDictionary;
  tooltip: typeof React.Component | React.FC<any>;
  isTemplate: boolean;
  commands: Record<string, any>;
  onShowToast?: (msg: string, code: string) => void;
  view: EditorView;
  linkIsActive?: boolean;
  onClose?: () => void;
  searchTriggerOpen?: boolean;
  resetSearchTrigger?: () => void;
  onCreateFlashcard?: (txt?: string, surroundTxt?: string) => void;
  onMakeAnswer?: (txt?: string, surroundTxt?: string) => void;
  LinkFinder?: typeof React.Component | React.FC<any>;
  getSelection?: () => Array<string>;
};

type State = {
  formatHidden: boolean;
};

function isActive(props) {
  const { view } = props;
  const { selection } = view.state;

  if (!selection) return false;
  if (selection.empty) return false;
  if (selection.node && selection.node.type.name === "image") {
    return true;
  }
  if (selection.node) return false;

  const slice = selection.content();
  const fragment = slice.content;
  const nodes = fragment.content;

  return some(nodes, n => n.content.size);
}

export default class SelectionToolbar extends React.Component<Props, State> {
  state = {
    formatHidden: !!this.props.onMakeAnswer,
  };

  handleOnSelectLink = ({
    href,
    from,
    to,
    title,
  }: {
    href: string;
    from: number;
    to: number;
    title: string;
  }): void => {
    const { view } = this.props;
    const { state, dispatch } = view;
    console.log(`title`, title, href, from, to);
    if (!from && !to) {
      from = state.selection.from;
      to = state.selection.to;
    }
    if (this.props.onClose) {
      this.props.onClose();
      this.props.view.focus();
    }
    const markType = state.schema.marks.link;

    if (from === to) {
      dispatch(
        view.state.tr
          .insertText(title, from, to)
          .addMark(
            from,
            to + title.length,
            state.schema.marks.link.create({ href })
          )
      );
    } else {
      dispatch(
        state.tr
          .removeMark(from, to, markType)
          .addMark(from, to, markType.create({ href }))
      );
    }
  };

  get suggestedLinkTitle(): string {
    const { state } = this.props.view;
    const selectionText = state.doc.cut(
      state.selection.from,
      state.selection.to
    ).textContent;

    return selectionText.trim() || "";
  }

  get href(): string {
    const { view } = this.props;
    const { state } = view;
    const { selection }: { selection: any } = state;
    const range = getMarkRange(selection.$from, state.schema.marks.link);
    const mark = range && range.mark;
    return mark ? mark.attrs.href : "";
  }

  render() {
    const { dictionary, isTemplate, onMakeAnswer, ...rest } = this.props;
    const { view } = rest;
    const { state, dispatch } = view;
    const { selection }: { selection: any } = state;
    const isCodeSelection = isNodeActive(state.schema.nodes.code_block)(state);

    // toolbar is disabled in code blocks, no bold / italic etc
    if (isCodeSelection) {
      return null;
    }

    const colIndex = getColumnIndex(state.selection);
    const rowIndex = getRowIndex(state.selection);
    const isTableSelection = colIndex !== undefined && rowIndex !== undefined;
    const link = isMarkActive(state.schema.marks.link)(state);
    // on iOS, native editor conflicts with link search menu and inline edit bar
    // we do need to keep link editing bar however
    // if (!link && (iOS() || android())) {
    //   return null;
    // }
    const range = getMarkRange(selection.$from, state.schema.marks.link);
    const isImageSelection =
      selection.node && selection.node.type.name === "image";

    const selectedText = getText(selection.content());

    let items: MenuItem[] = [];
    if (isTableSelection) {
      items = getTableMenuItems(dictionary);
    } else if (colIndex !== undefined) {
      items = getTableColMenuItems(state, colIndex, dictionary);
    } else if (rowIndex !== undefined) {
      items = getTableRowMenuItems(state, rowIndex, dictionary);
    } else if (isImageSelection) {
      items = getImageMenuItems(state, dictionary);
    } else if (onMakeAnswer && this.state.formatHidden) {
      items = [
        {
          name: "makeanswer",
          text: "⬇️ Make Answer",
          // tooltip: "Blank out selected text and move to answer section",
          // icon: LinkIcon,
          // active: true,
          // attrs: { href: "" },
        },
        {
          name: "separator",
        },
        {
          name: "format",
          text: "Format ...",
          // tooltip: dictionary.placeholder,
          // icon: InputIcon,
          // active: false,
          // visible: isTemplate,
        },
      ];
    } else {
      items = getFormattingMenuItems(state, isTemplate, dictionary);
      if (onMakeAnswer && !this.state.formatHidden) {
        items.unshift({
          name: "back",
          text: "<",
        });
      }
    }

    if (!items.length) {
      return null;
    }
    const { LinkFinder } = this.props;
    const MenuEl = (
      <Menu
        items={items}
        onToggleFormat={() =>
          this.setState({ formatHidden: !this.state.formatHidden })
        }
        onMakeAnswer={onMakeAnswer}
        {...rest}
      />
    );
    const LinkEditorEl =
      !this.href && LinkFinder ? (
        <LinkFinder
          callback={this.handleOnSelectLink}
          newSearchTerm={this.suggestedLinkTitle}
          onClose={() => {
            try {
              if (range && range.from && range.to && !this.href) {
                const markType = state.schema.marks.link;
                dispatch(
                  state.tr.removeMark(
                    (range && range.from) || 0,
                    (range && range.to) || 0,
                    markType
                  )
                );
              }
            } catch (e) {
              console.log(`error closing`, e);
            }

            this.props.onClose && this.props.onClose();
          }}
        />
      ) : (
        <LinkEditor
          dictionary={dictionary}
          mark={(range && range.mark) || undefined}
          from={(range && range.from) || selection.from}
          to={(range && range.to) || selection.to}
          onSelectLink={this.handleOnSelectLink}
          onRemoveLink={this.props.onClose}
          {...rest}
        />
      );

    return link && range && !this.href ? (
      LinkEditorEl
    ) : (
      <Portal>
        <FloatingToolbar view={view} active={isActive(this.props)}>
          {link && range ? LinkEditorEl : MenuEl}
        </FloatingToolbar>
      </Portal>
    );
  }
}
