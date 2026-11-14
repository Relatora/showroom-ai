/** A passage the assistant retrieved and may cite, as sent in the "sources" event. */
export type Source = {
    id: number;
    type: 'document' | 'vehicle' | 'lead' | 'activity';
    label: string;
    /** For documents: the section, e.g. "How long an offer is valid". */
    section: string | null;
    excerpt: string;
    url: string | null;
    score: number;
};

export type Timing = {
    retrieval_ms: number;
    first_token_ms: number | null;
    total_ms: number;
};

/** The events /assistant/ask streams, and what each one carries. */
export type AskEvents = {
    sources: { sources: Source[]; ms: number };
    delta: { text: string };
    done: Timing;
    error: { message: string };
};
