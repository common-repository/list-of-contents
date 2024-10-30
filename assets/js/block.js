const { registerBlockType } = wp.blocks;
const { useBlockProps } = wp.blockEditor;
const { useSelect } = wp.data;
const { createElement } = wp.element;

registerBlockType('locp/table-of-contents', {
    title: 'Table of Contents',
    icon: 'list-view',
    category: 'widgets',

    edit() {
        const blockProps = useBlockProps({ className: 'locp-toc-block' });

        const headings = useSelect((select) => {
            const blocks = select('core/block-editor').getBlocks();
            if (!blocks) return [];
            return blocks
                .filter(block => block.name === 'core/heading')
                .map(block => ({
                    id: block.clientId,
                    content: block.attributes.content || '',
                    level: block.attributes.level || 2,
                }));
        }, []);

        if (!headings || headings.length === 0) {
            return createElement('div', blockProps, 'No headings found.');
        }

        return createElement(
            'div',
            blockProps,
            createElement('h2', null, 'Table of Contents'),
            createElement(
                'ol',
                null,
                headings.map((heading, index) =>
                    createElement(
                        'li',
                        { key: index },
                        createElement(
                            'a',
                            { href: `#${heading.id}` },
                            heading.content
                        )
                    )
                )
            )
        );
    },

    save() {
        return null; // Save method handled by server-side rendering.
    },
});
