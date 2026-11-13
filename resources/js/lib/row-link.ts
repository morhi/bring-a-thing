/**
 * Style a linked row inside a divided list: hover tint and focus ring for light and dark.
 *
 * The negative margin lets the tint reach past the text without shifting the row content.
 */
export const rowLinkClass =
    'group text-fg hover:text-fg hover:bg-surface-muted focus-visible:bg-surface-muted -mx-2 rounded-lg px-2 no-underline transition-colors duration-(--duration-base) focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-solid focus-visible:outline-focus-ring motion-reduce:transition-none';
