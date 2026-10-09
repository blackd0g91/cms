import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import TagInput from '@/components/cp/TagInput.vue';

const mountInput = (tags: string[] = [], suggestions: string[] = []) =>
    mount(TagInput, {
        props: {
            id: 'tags',
            suggestions,
            modelValue: tags,
            'onUpdate:modelValue': (value: string[]) =>
                wrapper.setProps({ modelValue: value }),
        },
    });

let wrapper: ReturnType<typeof mountInput>;

const type = async (text: string, key = 'Enter') => {
    const input = wrapper.get('input');
    await input.setValue(text);
    await input.trigger('keydown', { key });
};

describe('TagInput', () => {
    it('adds a tag on Enter, and several at once with commas', async () => {
        wrapper = mountInput();

        await type('vegan');
        await type('quick,  weeknight  dinner , ');

        expect(wrapper.props('modelValue')).toEqual([
            'vegan',
            'quick',
            'weeknight dinner',
        ]);
        expect(wrapper.get('input').element.value).toBe('');
    });

    it('does not add a tag twice, whatever its case', async () => {
        wrapper = mountInput(['Vegan']);

        await type('vegan');

        expect(wrapper.props('modelValue')).toEqual(['Vegan']);
    });

    it('reuses the spelling of an existing tag', async () => {
        wrapper = mountInput([], ['Git', 'Laravel']);

        await type('git');

        expect(wrapper.props('modelValue')).toEqual(['Git']);
    });

    it('only suggests tags not added yet', () => {
        wrapper = mountInput(['Git'], ['Git', 'Laravel']);

        expect(
            wrapper
                .findAll('option')
                .map((option) => option.attributes('value')),
        ).toEqual(['Laravel']);
    });

    it('removes the last tag with Backspace in an empty input, or any with its button', async () => {
        wrapper = mountInput(['a', 'b', 'c']);

        await type('', 'Backspace');

        expect(wrapper.props('modelValue')).toEqual(['a', 'b']);

        await wrapper.get('button[aria-label="Remove tag a"]').trigger('click');

        expect(wrapper.props('modelValue')).toEqual(['b']);
    });
});
