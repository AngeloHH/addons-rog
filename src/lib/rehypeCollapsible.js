// Group headings at the requested level with their following content in details elements.
// A same-level or higher heading closes the group; source Markdown remains unchanged.
// The renderer decides whether sections are collapsible.
const levels = { h1: 1, h2: 2, h3: 3, h4: 4, h5: 5, h6: 6 };

export default function rehypeCollapsible({ level = 'h3' } = {}) {
  const target = levels[level];

  if (!target) return () => {};

  return (tree) => {
    const grouped = [];
    let open = null;

    /**
     * Close the current group at a heading of the same or a higher level.
     */
    function closes(node) {
      const own = levels[node.tagName];
      return Boolean(own) && own <= target;
    }

    for (const node of tree.children) {
      if (node.type === 'element' && node.tagName === level) {
        open = {
          type: 'element',
          tagName: 'details',
          properties: {},
          children: [
            { type: 'element', tagName: 'summary', properties: {}, children: [node] },
          ],
        };

        grouped.push(open);
        continue;
      }

      if (open && node.type === 'element' && closes(node)) {
        open = null;
      }

      if (open) {
        open.children.push(node);
      } else {
        grouped.push(node);
      }
    }

    tree.children = grouped;
  };
}
