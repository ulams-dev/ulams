/* global window File Promise */
import * as React from "react";
import memoize from "lodash/memoize";
import { EditorState, Selection, Plugin } from "prosemirror-state";
import { dropCursor } from "prosemirror-dropcursor";
import { gapCursor } from "prosemirror-gapcursor";
import { MarkdownParser } from "prosemirror-markdown";
import { MarkdownSerializer } from "./lib/markdown/serializer";
import { EditorView } from "prosemirror-view";
import { Schema, NodeSpec, MarkSpec, Slice } from "prosemirror-model";
import { inputRules, InputRule } from "prosemirror-inputrules";
import { keymap } from "prosemirror-keymap";
import { baseKeymap } from "prosemirror-commands";
import { selectColumn, selectRow, selectTable } from "prosemirror-utils";
import { light as lightTheme, dark as darkTheme } from "./theme";
import { cx, EditorThemeContext, themeScopeProps } from "./themeContext";
import "./styles/editor.css";
import baseDictionary from "./dictionary";
import Flex from "./components/Flex";
import { EmbedDescriptor, ToastType } from "./types";
import SelectionToolbar, { iOS, getText } from "./components/SelectionToolbar";
import BlockMenu from "./components/BlockMenu";
import LinkToolbar from "./components/LinkToolbar";
import Tooltip from "./components/Tooltip";
import Extension from "./lib/Extension";
import ExtensionManager from "./lib/ExtensionManager";
import ComponentView from "./lib/ComponentView";
import headingToSlug from "./lib/headingToSlug";
import { mathSerializer } from "@benrbray/prosemirror-math";

// nodes
import ReactNode from "./nodes/ReactNode";
import Doc from "./nodes/Doc";
import Text from "./nodes/Text";
import Blockquote from "./nodes/Blockquote";
import BulletList from "./nodes/BulletList";
import CodeBlock from "./nodes/CodeBlock";
import CodeFence from "./nodes/CodeFence";
import CheckboxList from "./nodes/CheckboxList";
import CheckboxItem from "./nodes/CheckboxItem";
import Embed from "./nodes/Embed";
import HardBreak from "./nodes/HardBreak";
import Heading from "./nodes/Heading";
import HorizontalRule from "./nodes/HorizontalRule";
import Image from "./nodes/Image";
import ListItem from "./nodes/ListItem";
import Math from "./nodes/Math";
import MathDisplay from "./nodes/MathDisplay";
import Notice from "./nodes/Notice";
import OrderedList from "./nodes/OrderedList";
import Paragraph from "./nodes/Paragraph";
import Table from "./nodes/Table";
import TableCell from "./nodes/TableCell";
import TableHeadCell from "./nodes/TableHeadCell";
import TableRow from "./nodes/TableRow";

// marks
import Bold from "./marks/Bold";
import Code from "./marks/Code";
import Highlight from "./marks/Highlight";
import Italic from "./marks/Italic";
import Link from "./marks/Link";
import Strikethrough from "./marks/Strikethrough";
import TemplatePlaceholder from "./marks/Placeholder";
import Underline from "./marks/Underline";

// plugins
import BlockMenuTrigger from "./plugins/BlockMenuTrigger";
import SearchTrigger from "./plugins/SearchTrigger";
import History from "./plugins/History";
import Keys from "./plugins/Keys";
import Placeholder from "./plugins/Placeholder";
import SmartText from "./plugins/SmartText";
import TrailingNode from "./plugins/TrailingNode";
import MarkdownPaste from "./plugins/MarkdownPaste";

export { schema, parser, serializer, renderToHtml } from "./server";

export { default as Extension } from "./lib/Extension";

export const theme = lightTheme;

// const getParent = (selection, state) => {
//   const selectionStart = selection.$from;
//   let depth = selectionStart.depth;
//   let parent;
//   do {
//     parent = selectionStart.node(depth);
//     if (parent) {
//       if (parent.type === state.schema.nodes.theNodeTypeImLookingFor) {
//         break;
//       }
//       depth--;
//     }
//   } while (depth > 0 && parent);
//   return parent;
// };

export type Props = {
  id?: string;
  value?: string;
  defaultValue: string;
  placeholder: string;
  extensions: Extension[];
  autoFocus?: boolean;
  readOnly?: boolean;
  readOnlyWriteCheckboxes?: boolean;
  dictionary?: Partial<typeof baseDictionary>;
  dark?: boolean;
  theme?: typeof theme;
  template?: boolean;
  headingsOffset?: number;
  scrollTo?: string;
  handleDOMEvents?: {
    [name: string]: (view: EditorView, event: Event) => boolean;
  };
  uploadImage?: (file: File) => Promise<string>;
  uploadSketch?: (file?: File) => Promise<string>;
  onSave?: (options: { done: boolean }) => void;
  onCancel?: () => void;
  onChange: (value: () => string) => void;
  onImageUploadStart?: () => void;
  onImageUploadStop?: () => void;
  LinkFinder?: typeof React.Component | React.FC<any>;
  upgradeCallback?: () => void;
  onClickLink: (href: string, event: MouseEvent) => void;
  enableTemplatePlaceholder?: boolean;
  templatePlaceholderAsQuestion?: boolean;
  getPlaceHolderLink: (title: string) => string;
  newLinePlaceholder?: string;
  onHoverLink?: (event: MouseEvent) => boolean;
  onClickHashtag?: (tag: string, event: MouseEvent) => void;
  onKeyDown?: (event: React.KeyboardEvent<HTMLDivElement>) => void;
  embeds: EmbedDescriptor[];
  onShowToast?: (message: string, code: ToastType) => void;
  tooltip: typeof React.Component | React.FC<any>;
  className?: string;
  style?: Record<string, string>;
  editorMinHeight?: string;
  onCreateFlashcard?: (txt?: string, surroundTxt?: string) => void;
  onMakeAnswer?: (txt?: string, surroundTxt?: string) => void;
  excludeBlockMenuItems?: Array<string>;
};

type State = {
  blockMenuOpen: boolean;
  linkMenuOpen: boolean;
  searchTriggerOpen: boolean;
  blockMenuSearch: string;
  focused: boolean;
};

type Step = {
  slice: Slice;
};

class RichMarkdownEditor extends React.PureComponent<Props, State> {
  static defaultProps = {
    defaultValue: "",
    placeholders: "Write note…",
    onImageUploadStart: () => {
      // no default behavior
    },
    onImageUploadStop: () => {
      // no default behavior
    },
    onClickLink: (href) => {
      window.open(href, "_blank");
    },
    getPlaceHolderLink: (title) => `/cards/${title}`,
    enableTemplatePlaceholder: true,
    templatePlaceholderAsQuestion: false,
    embeds: [],
    extensions: [],
    tooltip: Tooltip,
    newLinePlaceholder: "",
    onCreateFlashcard: null,
    onMakeAnswer: null,
    excludeBlockMenuItems: ["Image occlusion"],
  };

  state = {
    blockMenuOpen: false,
    linkMenuOpen: false,
    searchTriggerOpen: false,
    blockMenuSearch: "",
    focused: false,
  };

  extensions: ExtensionManager;
  element?: HTMLElement | null;
  view: EditorView;
  schema: Schema;
  serializer: MarkdownSerializer;
  parser: MarkdownParser;
  plugins: Plugin[];
  keymaps: Plugin[];
  inputRules: InputRule[];
  nodeViews: {
    [name: string]: (node, view, getPos, decorations) => ComponentView;
  };
  nodes: { [name: string]: NodeSpec };
  marks: { [name: string]: MarkSpec };
  commands: Record<string, any>;

  componentDidMount() {
    this.init();

    if (this.props.scrollTo) {
      this.scrollToAnchor(this.props.scrollTo);
    }

    if (this.props.readOnly) return;

    if (this.props.autoFocus) {
      this.focusAtEnd();
    }
  }

  componentDidUpdate(prevProps: Props) {
    // Allow changes to the 'value' prop to update the editor from outside
    if (this.props.value && prevProps.value !== this.props.value) {
      const newState = this.createState(this.props.value);
      this.view.updateState(newState);
    }

    // pass readOnly changes through to underlying editor instance
    if (prevProps.readOnly !== this.props.readOnly) {
      this.view.update({
        ...this.view.props,
        editable: () => !this.props.readOnly,
      });
    }

    if (this.props.scrollTo && this.props.scrollTo !== prevProps.scrollTo) {
      this.scrollToAnchor(this.props.scrollTo);
    }

    // Focus at the end of the document if switching from readOnly and autoFocus
    // is set to true
    if (prevProps.readOnly && !this.props.readOnly && this.props.autoFocus) {
      this.focusAtEnd();
    }
  }

  init() {
    this.extensions = this.createExtensions();
    this.nodes = this.createNodes();
    this.marks = this.createMarks();
    this.schema = this.createSchema();
    this.plugins = this.createPlugins();
    this.keymaps = this.createKeymaps();
    this.serializer = this.createSerializer();
    this.parser = this.createParser();
    this.inputRules = this.createInputRules();
    this.nodeViews = this.createNodeViews();
    this.view = this.createView();
    this.commands = this.createCommands();
  }

  createExtensions() {
    const dictionary = this.dictionary(this.props.dictionary);
    const templatePlaceHolderList = this.props.enableTemplatePlaceholder
      ? [new TemplatePlaceholder()]
      : [];
    // adding nodes here? Update schema.ts for serialization on the server
    return new ExtensionManager(
      [
        new Doc(),
        new Text(),
        new HardBreak(),
        new Paragraph(),
        new Blockquote(),
        new CodeBlock({
          dictionary,
          initialReadOnly: this.props.readOnly,
          onShowToast: this.props.onShowToast,
        }),
        new CodeFence({
          dictionary,
          initialReadOnly: this.props.readOnly,
          onShowToast: this.props.onShowToast,
        }),
        new CheckboxList(),
        new CheckboxItem(),
        new BulletList(),
        new Embed(),
        new ListItem(),
        new Notice({
          dictionary,
        }),
        new Heading({
          dictionary,
          onShowToast: this.props.onShowToast,
          offset: this.props.headingsOffset,
        }),
        new HorizontalRule(),
        new Image({
          dictionary,
          uploadImage: this.props.uploadImage,
          uploadSketch: this.props.uploadSketch,
          embeds: this.props.embeds,
          onImageUploadStart: this.props.onImageUploadStart,
          onImageUploadStop: this.props.onImageUploadStop,
          onShowToast: this.props.onShowToast,
        }),
        new Table(),
        new TableCell({
          onSelectTable: this.handleSelectTable,
          onSelectRow: this.handleSelectRow,
        }),
        new TableHeadCell({
          onSelectColumn: this.handleSelectColumn,
        }),
        new TableRow(),
        new Bold(),
        new Code(),
        new Highlight(),
        new Italic(),
        ...templatePlaceHolderList,
        new Math(),
        new MathDisplay(),
        new Underline(),
        new Link({
          onKeyboardShortcut: this.handleOpenLinkMenu,
          onClickLink: this.props.onClickLink,
          onClickHashtag: this.props.onClickHashtag,
          onHoverLink: this.props.onHoverLink,
        }),
        new Strikethrough(),
        new OrderedList(),
        new History(),
        new SmartText(),
        new TrailingNode(),
        new MarkdownPaste({ onPaste: () => {} }),
        new Keys({
          onSave: this.handleSave,
          onSaveAndExit: this.handleSaveAndExit,
          onCancel: this.props.onCancel,
        }),
        new BlockMenuTrigger({
          dictionary,
          onOpen: this.handleOpenBlockMenu,
          onClose: this.handleCloseBlockMenu,
          newLinePlaceholder: this.props.newLinePlaceholder,
        }),
        new SearchTrigger({
          onOpen: () => {
            this.handleOpenLinkMenu();
            this.setState({ searchTriggerOpen: true });
          },
        }),
        new Placeholder({
          placeholder: this.props.placeholder,
        }),
        ...this.props.extensions,
      ],
      this
    );
  }

  createPlugins() {
    return this.extensions.plugins;
  }

  createKeymaps() {
    return this.extensions.keymaps({
      schema: this.schema,
    });
  }

  createInputRules() {
    return this.extensions.inputRules({
      schema: this.schema,
    });
  }

  createNodeViews() {
    return this.extensions.extensions
      .filter((extension: ReactNode) => extension.component)
      .reduce((nodeViews, extension: ReactNode) => {
        const nodeView = (node, view, getPos, decorations) => {
          return new ComponentView(extension.component, {
            editor: this,
            extension,
            node,
            view,
            getPos,
            decorations,
          });
        };

        return {
          ...nodeViews,
          [extension.name]: nodeView,
        };
      }, {});
  }

  createCommands() {
    return this.extensions.commands({
      schema: this.schema,
      view: this.view,
    });
  }

  createNodes() {
    return this.extensions.nodes;
  }

  createMarks() {
    return this.extensions.marks;
  }

  createSchema() {
    return new Schema({
      nodes: this.nodes,
      marks: this.marks,
    });
  }

  createSerializer() {
    return this.extensions.serializer();
  }

  createParser() {
    return this.extensions.parser({
      schema: this.schema,
    });
  }

  createState(value?: string) {
    const doc = this.createDocument(value || this.props.defaultValue);

    return EditorState.create({
      schema: this.schema,
      doc,
      plugins: [
        ...this.plugins,
        ...this.keymaps,
        dropCursor({ color: this.theme().cursor || "black" }),
        gapCursor(),
        inputRules({
          rules: this.inputRules,
        }),
        keymap(baseKeymap),
      ],
    });
  }

  createDocument(content: string) {
    // FIXME when pasting html this sometimes unnecessarily escapes resulting markdown
    return this.parser.parse(content);
  }

  createView() {
    if (!this.element) {
      throw new Error("createView called before ref available");
    }

    const isEditingCheckbox = (tr) => {
      return tr.steps.some(
        (step: Step) =>
          step.slice?.content?.firstChild?.type?.name ===
          this.schema.nodes.checkbox_item.name
      );
    };

    const view = new EditorView(this.element, {
      state: this.createState(),
      editable: () => !this.props.readOnly,
      nodeViews: this.nodeViews,
      handleDOMEvents: this.props.handleDOMEvents,
      clipboardTextSerializer: (slice) => mathSerializer.serializeSlice(slice),
      dispatchTransaction: (transaction) => {
        const { state, transactions } = this.view.state.applyTransaction(
          transaction
        );

        this.view.updateState(state);

        // If any of the transactions being dispatched resulted in the doc
        // changing then call our own change handler to let the outside world
        // know
        if (
          transactions.some((tr) => tr.docChanged) &&
          (!this.props.readOnly ||
            (this.props.readOnlyWriteCheckboxes &&
              transactions.some(isEditingCheckbox)))
        ) {
          this.handleChange();
        }

        // Because Prosemirror and React are not linked we must tell React that
        // a render is needed whenever the Prosemirror state changes.
        this.forceUpdate();
      },
    });

    return view;
  }

  scrollToAnchor(hash: string) {
    if (!hash) return;

    try {
      const element = document.querySelector(hash);
      if (element) element.scrollIntoView({ behavior: "smooth" });
    } catch (err) {
      // querySelector will throw an error if the hash begins with a number
      // or contains a period. This is protected against now by safeSlugify
      // however previous links may be in the wild.
      console.warn(`Attempted to scroll to invalid hash: ${hash}`, err);
    }
  }

  value = (): string => {
    return this.serializer.serialize(this.view.state.doc);
  };

  handleChange = () => {
    if (!this.props.onChange) return;

    this.props.onChange(() => {
      return this.value();
    });
  };

  handleSave = () => {
    const { onSave } = this.props;
    if (onSave) {
      onSave({ done: false });
    }
  };

  handleSaveAndExit = () => {
    const { onSave } = this.props;
    if (onSave) {
      onSave({ done: true });
    }
  };

  handleOpenLinkMenu = () => {
    this.setState({ linkMenuOpen: true });
  };

  handleCloseLinkMenu = () => {
    console.log(`close`);
    this.setState({ linkMenuOpen: false });
  };

  handleOpenBlockMenu = (search: string) => {
    this.setState({ blockMenuOpen: true, blockMenuSearch: search });
  };

  handleCloseBlockMenu = () => {
    if (!this.state.blockMenuOpen) return;
    this.setState({ blockMenuOpen: false });
  };

  handleSelectRow = (index: number, state: EditorState) => {
    this.view.dispatch(selectRow(index)(state.tr));
  };

  handleSelectColumn = (index: number, state: EditorState) => {
    this.view.dispatch(selectColumn(index)(state.tr));
  };

  handleSelectTable = (state: EditorState) => {
    this.view.dispatch(selectTable(state.tr));
  };

  // 'public' methods
  focusAtStart = () => {
    const selection = Selection.atStart(this.view.state.doc);
    const transaction = this.view.state.tr.setSelection(selection);
    this.view.dispatch(transaction);
    this.view.focus();
  };

  focusAtEnd = () => {
    const selection = Selection.atEnd(this.view.state.doc);
    const transaction = this.view.state.tr.setSelection(selection);
    this.view.dispatch(transaction);
    this.view.focus();
  };

  getHeadings = () => {
    const headings: { title: string; level: number; id: string }[] = [];
    const previouslySeen = {};

    this.view.state.doc.forEach((node) => {
      if (node.type.name === "heading") {
        // calculate the optimal slug
        const slug = headingToSlug(node);
        let id = slug;

        // check if we've already used it, and if so how many times?
        // Make the new id based on that number ensuring that we have
        // unique ID's even when headings are identical
        if (previouslySeen[slug] > 0) {
          id = headingToSlug(node, previouslySeen[slug]);
        }

        // record that we've seen this slug for the next loop
        previouslySeen[slug] =
          previouslySeen[slug] !== undefined ? previouslySeen[slug] + 1 : 1;

        headings.push({
          title: node.textContent,
          level: node.attrs.level,
          id,
        });
      }
    });
    return headings;
  };

  getSelection = () => {
    console.log(`selection`);
    const selection = this.view?.state?.selection;
    const selectionContent = selection?.content();
    const selectedText = (selectionContent && getText(selectionContent)) || "";
    // const parent = getParent(selection, this.view.state);
    // const surroundingText = parent ? getText(parent) : selectedText;
    return [selectedText, this.value()];
  };

  theme = () => {
    return {
      ...(this.props.dark ? darkTheme : lightTheme),
      ...(this.props.theme || {}),
    };
  };

  dictionary = memoize(
    (providedDictionary?: Partial<typeof baseDictionary>) => {
      return { ...baseDictionary, ...providedDictionary };
    }
  );

  render = () => {
    const {
      readOnly,
      readOnlyWriteCheckboxes,
      style,
      tooltip,
      className,
      onKeyDown,
    } = this.props;
    const dictionary = this.dictionary(this.props.dictionary);
    // Resolved theme for JS consumers; CSS reads the --md-* variables set by `scope`.
    const themeContext = {
      theme: this.theme(),
      dark: this.props.dark,
      overrides: this.props.theme,
    };
    const scope = themeScopeProps(themeContext, className, style);

    return (
      <EditorThemeContext.Provider value={themeContext}>
        <Flex
          onKeyDown={onKeyDown}
          style={scope.style}
          className={scope.className}
          align="flex-start"
          justify="flex-start"
          column
          onFocus={() => this.setState({ focused: true })}
          onBlur={(event) => {
            if (
              event.relatedTarget &&
              !event.currentTarget.contains(event.relatedTarget as any)
            ) {
              this.setState({ focused: false });
            }
            if (
              !event.relatedTarget ||
              (document.getElementById("block-menu-container") &&
                !document.getElementById("block-menu-container") &&
                (document.getElementById(
                  "block-menu-container"
                ) as any).contains(event.relatedTarget as any) &&
                !(event.relatedTarget as any).className.includes(
                  "block-menu-trigger"
                ))
            ) {
              this.handleCloseBlockMenu();
            }
          }}
        >
          <React.Fragment>
            <div
              style={{
                minHeight: this.props.editorMinHeight
                  ? this.props.editorMinHeight
                  : undefined,
              }}
              className={cx(
                "ulams-md-editor",
                readOnly && "ulams-md-editor--readonly",
                readOnlyWriteCheckboxes &&
                  "ulams-md-editor--readonly-write-checkboxes",
                this.props.templatePlaceholderAsQuestion &&
                  "ulams-md-editor--answer-placeholder",
                iOS() && "ulams-md-editor--ios"
              )}
              ref={(ref) => (this.element = ref)}
            />
            {!readOnly && this.view && (
              <React.Fragment>
                <SelectionToolbar
                  view={this.view}
                  dictionary={dictionary}
                  commands={this.commands}
                  isTemplate={this.props.template === true}
                  onCreateFlashcard={this.props.onCreateFlashcard}
                  onMakeAnswer={this.props.onMakeAnswer}
                  tooltip={tooltip}
                  onClose={this.handleCloseLinkMenu}
                  LinkFinder={this.props.LinkFinder}
                  getSelection={this.getSelection}
                />
                <LinkToolbar
                  view={this.view}
                  dictionary={dictionary}
                  isActive={this.state.linkMenuOpen}
                  onShowToast={this.props.onShowToast}
                  onClose={this.handleCloseLinkMenu}
                  tooltip={tooltip}
                  LinkFinder={this.props.LinkFinder}
                  searchTriggerOpen={this.state.searchTriggerOpen}
                  resetSearchTrigger={() =>
                    this.setState({ searchTriggerOpen: false })
                  }
                />

                <BlockMenu
                  view={this.view}
                  commands={this.commands}
                  dictionary={dictionary}
                  isActive={this.state.blockMenuOpen}
                  search={this.state.blockMenuSearch}
                  onClose={this.handleCloseBlockMenu}
                  uploadImage={this.props.uploadImage}
                  uploadSketch={this.props.uploadSketch}
                  onLinkToolbarOpen={this.handleOpenLinkMenu}
                  onImageUploadStart={this.props.onImageUploadStart}
                  onImageUploadStop={this.props.onImageUploadStop}
                  onShowToast={this.props.onShowToast}
                  embeds={this.props.embeds}
                  excludeBlockMenuItems={this.props.excludeBlockMenuItems}
                  upgradeCallback={this.props.upgradeCallback}
                />
              </React.Fragment>
            )}
          </React.Fragment>
        </Flex>
      </EditorThemeContext.Provider>
    );
  };
}

export default RichMarkdownEditor;
