import { mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, nextTick, ref } from 'vue';
import {
    listLocalDrafts,
    removeLocalDraft,
    useLocalDraft,
} from '@/composables/useLocalDraft';

type Data = { title: string };

// A form that keeps its draft under "post.1", dirty once the title changes.
const mountForm = () => {
    const title = ref('Soup');
    let draft!: ReturnType<typeof useLocalDraft<Data>>;

    const wrapper = mount(
        defineComponent({
            setup() {
                draft = useLocalDraft<Data>({
                    key: () => 'post.1',
                    data: () => ({ title: title.value }),
                    isDirty: () => title.value !== 'Soup',
                    version: () => 'v1',
                });

                return () => null;
            },
        }),
    );

    return { title, wrapper, draft: () => draft };
};

const stored = () =>
    JSON.parse(localStorage.getItem('cp.draft.post.1') ?? 'null');

describe('useLocalDraft', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-09T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
        localStorage.clear();
    });

    it('stores unsaved changes shortly after they are made', async () => {
        const { title } = mountForm();

        title.value = 'Tomato soup';
        await nextTick();

        expect(stored()).toBeNull();

        vi.advanceTimersByTime(800);

        expect(stored()).toEqual({
            data: { title: 'Tomato soup' },
            // When it was stored, after the wait.
            savedAt: '2026-10-09T12:00:00.800Z',
            version: 'v1',
        });
    });

    it('forgets the draft once the form is back to what was saved', async () => {
        const { title } = mountForm();

        title.value = 'Tomato soup';
        await nextTick();
        vi.advanceTimersByTime(800);
        title.value = 'Soup';
        await nextTick();
        vi.advanceTimersByTime(800);

        expect(stored()).toBeNull();
    });

    it('writes a pending change right away when the form closes', async () => {
        const { title, wrapper } = mountForm();

        title.value = 'Tomato soup';
        await nextTick();
        wrapper.unmount();

        expect(stored().data).toEqual({ title: 'Tomato soup' });
    });

    it('offers a draft found on load, without applying it', () => {
        localStorage.setItem(
            'cp.draft.post.1',
            JSON.stringify({
                data: { title: 'Older idea' },
                savedAt: '2026-10-08T09:00:00Z',
                version: 'v0',
            }),
        );

        const { title, draft } = mountForm();

        expect(draft().found.value?.data).toEqual({ title: 'Older idea' });
        expect(title.value).toBe('Soup');

        draft().discard();

        expect(draft().found.value).toBeNull();
        expect(stored()).toBeNull();
    });

    it('lists and removes drafts by key', () => {
        const draft = {
            data: {},
            savedAt: '2026-10-09T12:00:00Z',
            version: null,
        };
        localStorage.setItem('cp.draft.post.1', JSON.stringify(draft));
        localStorage.setItem('cp.draft.post.2', JSON.stringify(draft));
        localStorage.setItem('cp.draft.template.1', JSON.stringify(draft));
        localStorage.setItem('cp.draft.post.3', 'not json');

        expect(
            listLocalDrafts('post.')
                .map((entry) => entry.key)
                .sort(),
        ).toEqual(['post.1', 'post.2']);

        removeLocalDraft('post.1');

        expect(listLocalDrafts('post.').map((entry) => entry.key)).toEqual([
            'post.2',
        ]);
    });
});
