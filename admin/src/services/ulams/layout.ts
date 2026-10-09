import manifest from '../../../../front/ui/catalogue/learner-layout-manifest.json';
import { validate } from '../../../../front/ui/src/schema';

/**
 * Layout topics (api/packages/topic-type-layout, ADR 0052): a JSON list of catalogue nodes
 * `{ component, props, id? }` plus a Markdown fallback. The document is checked here against the same
 * learner layout manifest the API validates against, so the editor can show problems before saving.
 */

export type LayoutNode = { component: string; props?: Record<string, unknown>; id?: string };

export type LayoutTopicable = {
  id?: number;
  document?: LayoutNode[] | string;
  schema_version?: string;
  markdown_fallback?: string;
};

type Manifest = { components: Record<string, { description: string; props: any }> };

const components = (manifest as unknown as Manifest).components;

/** The approved components, in manifest order. */
export const layoutComponents = (): string[] => Object.keys(components);

/** Same limit as the API (LayoutDocumentValidator::MAX_NODES). */
export const MAX_LAYOUT_NODES = 80;

export type LayoutCheck = { document?: LayoutNode[]; errors: string[] };

const isObject = (value: unknown): value is Record<string, unknown> =>
  !!value && typeof value === 'object' && !Array.isArray(value);

/** Parses and validates the text of the editor. `document` is set only when `errors` is empty. */
export function checkLayoutText(text: string): LayoutCheck {
  if (text.trim() === '') {
    return { errors: ['/: the document is empty'] };
  }
  let parsed: unknown;
  try {
    parsed = JSON.parse(text);
  } catch (e: any) {
    return { errors: [`/: not valid JSON (${e?.message ?? 'parse error'})`] };
  }
  return checkLayoutDocument(parsed);
}

/** The rules of the API validator: a non-empty list of approved nodes with props that fit the manifest. */
export function checkLayoutDocument(document: unknown): LayoutCheck {
  if (!Array.isArray(document) || document.length === 0) {
    return { errors: ['/: the document must be a non-empty list of {component, props} nodes'] };
  }
  if (document.length > MAX_LAYOUT_NODES) {
    return { errors: [`/: a layout holds at most ${MAX_LAYOUT_NODES} nodes`] };
  }
  const errors: string[] = [];
  document.forEach((node, index) => {
    const at = `/${index}`;
    if (!isObject(node)) {
      errors.push(`${at}: a node must be an object with a component and props`);
      return;
    }
    const unknown = Object.keys(node).filter((key) => !['component', 'props', 'id'].includes(key));
    if (unknown.length > 0) {
      errors.push(`${at}: unknown key ${unknown.join(', ')} (only component, props and id are allowed)`);
    }
    if (node.id !== undefined && (typeof node.id !== 'string' || !/^[A-Za-z0-9_-]{1,40}$/.test(node.id))) {
      errors.push(`${at}/id: letters, digits, dashes and underscores, at most 40 characters`);
    }
    const name = node.component;
    if (typeof name !== 'string' || !components[name]) {
      errors.push(
        `${at}/component: ${typeof name === 'string' ? `'${name}'` : 'missing'} is not an approved layout component (allowed: ${layoutComponents().join(', ')})`,
      );
      return;
    }
    const props = node.props === undefined ? {} : node.props;
    if (!isObject(props)) {
      errors.push(`${at}/props: must be an object`);
      return;
    }
    validate(components[name].props, props).issues.forEach((issue) => {
      errors.push(`${at}/props${issue.path === '/' ? '' : issue.path}: ${issue.message}`);
    });
  });
  return errors.length > 0 ? { errors: errors.slice(0, 20) } : { document: document as LayoutNode[], errors: [] };
}

/** Text for the editor from what the API returned. */
export const layoutText = (document: LayoutTopicable['document']): string => {
  if (document === undefined || document === null) {
    return '';
  }
  if (typeof document === 'string') {
    return document;
  }
  return JSON.stringify(document, null, 2);
};

/** A small valid document to start from: a callout and a timeline. */
export const LAYOUT_STARTER: LayoutNode[] = [
  { component: 'Callout', props: { tone: 'tip', title: 'Start here', text: 'One sentence that says what this lesson is for.' } },
  {
    component: 'Timeline',
    props: {
      title: 'How it happened',
      items: [
        { label: 'First', title: 'The first step', text: 'What happened.' },
        { label: 'Then', title: 'The next step', text: 'What changed.' },
      ],
    },
  },
];

/**
 * Form fields of the topic. The document goes as JSON text because the admin posts topics as
 * multipart form data (the API accepts a JSON string for `document`).
 */
export const layoutFields = (text: string, fallback: string): Record<string, unknown> => ({
  document: text,
  markdown_fallback: fallback,
});

/**
 * Link to the author preview of the topic in the learner site (`/preview/courses/:course/:topic`).
 * The preview needs a saved topic and a course the author may edit. `learnerUrl` is the learner site
 * origin (the demo config, or the tenant's `app` host next to the `admin` one); null hides the link.
 */
export function layoutPreviewHref(learnerUrl: string | null | undefined, courseId?: number, topicId?: number): string | null {
  if (!learnerUrl || !courseId || !topicId) {
    return null;
  }
  return `${learnerUrl.replace(/\/+$/, '')}/preview/courses/${courseId}/${topicId}`;
}
