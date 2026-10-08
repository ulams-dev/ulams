import { setBlockType } from "prosemirror-commands";
import Node from "./Node";

export default class Paragraph extends Node {
  get name() {
    return "paragraph";
  }

  get schema() {
    return {
      attrs: {
        layoutClass: { default: null },
      },
      content: "inline*",
      group: "block",
      parseDOM: [
        {
          tag: "p",
          getAttrs: (dom) => this.extractLayoutClassFromDOM(dom),
        },
      ],
      toDOM: (node) => this.createDOMAttributes(node),
    };
  }

  keys({ type }) {
    return {
      "Shift-Ctrl-0": setBlockType(type),
    };
  }

  commands({ type }) {
    const alignText = (state, dispatch, layoutClass) => {
      const attrs = { layoutClass };
      const { selection } = state;
      const pos = selection.$from.pos - 1;
      const node = state.doc.nodeAt(pos);
      if (node) {
        const newAttrs = { ...node.attrs, ...attrs };
        const tr = state.tr.setNodeMarkup(pos, null, newAttrs);
        dispatch(tr);
        return true;
      }
      return false;
    };

    return {
      setBlockType: () => setBlockType(type),
      alignTextRight: () => (state, dispatch) =>
        alignText(state, dispatch, "right"),
      alignTextLeft: () => (state, dispatch) =>
        alignText(state, dispatch, "left"),
      alignTextCenter: () => (state, dispatch) =>
        alignText(state, dispatch, "center"),
    };
  }

  toMarkdown(state, node) {
    if (
      node.textContent.trim() === "" &&
      node.childCount === 0 &&
      !state.inTable
    ) {
      state.write("\\\n");
    } else {
      state.renderInline(node);
      state.closeBlock(node);
    }
  }

  parseMarkdown() {
    return { block: "paragraph" };
  }

  extractLayoutClassFromDOM(dom) {
    const className = dom.className;
    const layoutClassMatched = className && className.match(/text-(.*)$/);
    const layoutClass = layoutClassMatched ? layoutClassMatched[1] : null;
    return { layoutClass };
  }

  createDOMAttributes(node) {
    const className = node.attrs.layoutClass
      ? `text-${node.attrs.layoutClass}`
      : null;

    return ["p", { class: className }, 0];
  }
}
