/**
 * Addons ROG - Markdown
 *
 * Render Markdown with the shared site styles through react-markdown.
 *
 * Supported document conventions:
 * - A blockquote beginning with bold text uses that text as its colored label.
 * - Text after a fenced block's language becomes its label, such as ```text Correct.
 *
 * collapsible  A heading level such as h3; groups that heading and its content in details.
 *
 * Collapsible groups follow the document structure without requiring custom Markdown.
 */

import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import rehypeCollapsible from '../../lib/rehypeCollapsible.js';
import { slugify } from '../../lib/markdown.js';
import styles from './Markdown.module.scss';

function textOf(node) {
  if (!node) return '';
  if (node.type === 'text') return node.value;

  return (node.children ?? []).map(textOf).join('');
}

/**
 * Read the callout label from the first bold text in a blockquote.
 */
function labelOf(node) {
  const paragraph = (node?.children ?? []).find((child) => child.tagName === 'p');
  const strong = (paragraph?.children ?? []).find((child) => child.tagName === 'strong');

  return strong ? textOf(strong) : null;
}

const components = {
  h2: ({ children, node, ...rest }) => (
    <h2 className={styles.heading} id={slugify(textOf(node))} {...rest}>
      {children}
    </h2>
  ),

  h3: ({ children, node, ...rest }) => (
    <h3 className={styles.subheading} id={slugify(textOf(node))} {...rest}>
      {children}
    </h3>
  ),

  // Resume ordered lists at the start number supplied by Markdown.
  ol: ({ children, node, start, ...rest }) => (
    <ol start={start} style={start ? { counterReset: `step ${start - 1}` } : undefined} {...rest}>
      {children}
    </ol>
  ),

  blockquote: ({ children, node, ...rest }) => (
    <blockquote className={styles.callout} data-label={labelOf(node)} {...rest}>
      {children}
    </blockquote>
  ),

  pre: ({ children, node, ...rest }) => {
    const code = (node?.children ?? []).find((child) => child.tagName === 'code');
    const label = code?.data?.meta ?? null;

    return (
      <div className={styles.codeBlock} data-label={label}>
        {label && <span className={styles.codeLabel}>{label}</span>}
        <pre className={styles.pre} {...rest}>
          {children}
        </pre>
      </div>
    );
  },

  code: ({ children, className, ...rest }) =>
    className ? (
      <code className={className} {...rest}>
        {children}
      </code>
    ) : (
      <code className={styles.inlineCode} {...rest}>
        {children}
      </code>
    ),

  details: ({ children, ...rest }) => (
    <details className={styles.collapsible} {...rest}>
      {children}
    </details>
  ),

  summary: ({ children, ...rest }) => (
    <summary className={styles.summary} {...rest}>
      {children}
    </summary>
  ),

  table: ({ children, ...rest }) => (
    <div className={styles.tableWrap}>
      <table className={styles.table} {...rest}>
        {children}
      </table>
    </div>
  ),
};

export default function Markdown({ collapsible, content, resolveLink }) {
  const rehypePlugins = collapsible ? [[rehypeCollapsible, { level: collapsible }]] : [];
  const renderers = resolveLink ? {
    ...components,
    a: ({ children, href, node, ...rest }) => {
      const target = resolveLink(href);

      return target === null
        ? <span title={href}>{children}</span>
        : <a href={target} {...rest}>{children}</a>;
    },
  } : components;

  return (
    <div className={styles.document}>
      <ReactMarkdown
        components={renderers}
        rehypePlugins={rehypePlugins}
        remarkPlugins={[remarkGfm]}
      >
        {content}
      </ReactMarkdown>
    </div>
  );
}
