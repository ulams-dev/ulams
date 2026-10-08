import assert from "assert";
import * as React from "react";
import { EditorView } from "prosemirror-view";
import LinkEditor, { SearchResult } from "./LinkEditor";
import FloatingToolbar from "./FloatingToolbar";
import baseDictionary from "../dictionary";

type Props = {
  isActive: boolean;
  view: EditorView;
  tooltip: typeof React.Component | React.FC<any>;
  dictionary: typeof baseDictionary;
  onShowToast?: (msg: string, code: string) => void;
  onClose: () => void;
  searchTriggerOpen?: boolean;
  resetSearchTrigger?: () => void;
  LinkFinder?: typeof React.Component | React.FC<any>;
};

export function isActive(props) {
  const { view } = props;
  const { selection } = view.state;

  const paragraph = view.domAtPos(selection.$from.pos);
  return props.isActive && !!paragraph.node;
}

export default class LinkToolbar extends React.Component<Props> {
  menuRef = React.createRef<HTMLDivElement>();

  state = {
    left: -1000,
    top: undefined,
  };

  handleOnSelectLink = ({ href, title }: { href: string; title: string }) => {
    const { view, onClose } = this.props;
    console.log(`handleOnSelectLink`, href, title);

    onClose();
    this.props.view.focus();

    const { dispatch, state } = view;
    let { from, to } = state.selection;
    assert(from === to);
    if (this.props.searchTriggerOpen) {
      this.props.resetSearchTrigger && this.props.resetSearchTrigger();
      dispatch(view.state.tr.delete(from - 2, from));
      from = from - 2;
      to = to - 2;
    }

    dispatch(
      view.state.tr
        .insertText(title, from, to)
        .addMark(
          from,
          to + title.length,
          state.schema.marks.link.create({ href })
        )
    );
  };

  get suggestedLinkTitle(): string {
    const { state } = this.props.view;
    const selectionText = state.doc.cut(
      state.selection.from,
      state.selection.to
    ).textContent;

    return selectionText.trim() || "";
  }

  render() {
    const { LinkFinder } = this.props;

    return isActive(this.props) && LinkFinder ? (
      <LinkFinder
        callback={this.handleOnSelectLink}
        newSearchTerm={this.suggestedLinkTitle}
        onClose={this.props.onClose}
      />
    ) : null;
  }
}
