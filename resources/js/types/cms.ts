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
    author_id: number | null;
    pinned: boolean;
    tags: string[];
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
    pinned: boolean;
    updated_at: string;
    template: { id: number; name: string };
    author: string | null;
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

export type PostRevisionSummary = {
    id: number;
    title: string;
    status: PostStatus;
    created_at: string;
    user: string | null;
};

export type PostRevision = {
    id: number;
    title: string;
    slug: string;
    status: PostStatus;
    thumbnail_id: number | null;
    data: Record<string, FieldValue>;
    created_at: string;
};

// Views on one day, as "YYYY-MM-DD".
export type DayViews = {
    date: string;
    views: number;
};

export type Author = {
    id: number;
    name: string;
};

/** A link for choosing a password, made on the users page. */
export type PasswordLink = {
    url: string;
    expires_at: string;
    name: string;
    // Inviting them, rather than a new password for someone who has one.
    invited: boolean;
};

/** The site's logo from the settings, shared with every control panel page. */
export type SiteLogo = {
    // Null without a logo, or once its image is deleted.
    url: string | null;
    // A CSS declaration like "background: #c2410c", or null for none.
    background: string | null;
};
