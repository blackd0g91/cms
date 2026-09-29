export type FieldType =
    | 'text'
    | 'textarea'
    | 'markdown'
    | 'number'
    | 'boolean'
    | 'select'
    | 'date'
    | 'list'
    | 'image';

export type Field = {
    handle: string;
    label: string;
    type: FieldType;
    required: boolean;
    options: string[];
};

export type FieldTypeOption = {
    value: FieldType;
    label: string;
};

export type Template = {
    id: number;
    name: string;
    handle: string;
    description: string | null;
    color: string | null;
    fields: Field[];
    layout: string;
};

export type TemplateSummary = Pick<Template, 'id' | 'name' | 'handle'>;

export type FieldValue = string | number | boolean | string[] | null;

export type PostStatus = 'draft' | 'published';

export type Post = {
    id: number;
    title: string;
    slug: string;
    status: PostStatus;
    published_at: string | null;
    updated_at: string;
    thumbnail_id: number | null;
    data: Record<string, FieldValue>;
    url: string;
};

export type Media = {
    id: number;
    url: string;
    // A small version for grids and pickers (the original if there is none).
    thumb_url?: string;
    filename: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    alt: string | null;
    created_at: string;
};

export type PostListItem = {
    id: number;
    title: string;
    status: PostStatus;
    updated_at: string;
    template: { id: number; name: string };
    url: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type MediaUsage = {
    label: string;
    post_id: number | null;
    template_id: number | null;
};
