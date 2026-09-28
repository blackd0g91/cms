export type FieldType =
    | 'text'
    | 'textarea'
    | 'markdown'
    | 'number'
    | 'boolean'
    | 'select'
    | 'date'
    | 'list';

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
    data: Record<string, FieldValue>;
    url: string;
};
