/**
 * Builds resources/js/data/emoji.json from the emojibase dataset:
 *
 *   npm run emoji:data
 *
 * The file is committed. The control panel's emoji picker and the
 * :shortcode: support in markdown (app/Cms/Emoji.php) both read it.
 */
import { readFileSync, writeFileSync } from 'node:fs';

const read = (file) =>
    JSON.parse(
        readFileSync(
            new URL(
                `../node_modules/emojibase-data/en/${file}`,
                import.meta.url,
            ),
        ),
    );

const emoji = read('compact.json');
const shortcodes = read('shortcodes/github.json');
const { groups } = read('messages.json');

// "Components" are skin tone and hair swatches, not emoji on their own.
const COMPONENT_GROUP = 2;

const data = {
    groups: groups
        .filter((group) => group.order !== COMPONENT_GROUP)
        .map((group) => ({ id: group.order, name: group.message })),
    emoji: emoji
        .filter(
            (item) =>
                item.group !== undefined && item.group !== COMPONENT_GROUP,
        )
        .sort((a, b) => a.order - b.order)
        .map((item) => ({
            e: item.unicode,
            n: item.label,
            g: item.group,
            k: item.tags ?? [],
            s: [shortcodes[item.hexcode] ?? []].flat(),
        })),
};

writeFileSync(
    new URL('../resources/js/data/emoji.json', import.meta.url),
    JSON.stringify(data),
);

console.log(
    `Wrote ${data.emoji.length} emoji in ${data.groups.length} groups.`,
);
