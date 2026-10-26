import {
    computed,
    getCurrentScope,
    nextTick,
    onScopeDispose,
    ref,
    watch,
} from 'vue';
import type { PracticeQuestionForm, PracticeSessionData } from '@/types';

/**
 * What the indicator shows. The editor always sits in exactly one of these:
 *
 * - `needs_topic` — there is something to save, but no topic yet.
 * - `saving` — changes are waiting for the pause, or a save is in flight.
 * - `saved` — everything currently on screen is saved.
 * - `retrying` — a save failed and another attempt is scheduled.
 * - `failed` — a save failed and needs the learner (fix a field or retry).
 * - `conflict` — another tab changed the session first.
 * - `archived` — the session was archived elsewhere, so saves are refused
 *   until it is restored.
 * - `deleted` — the session was deleted, so there is nothing left to save
 *   into. The text stays on screen while the learner returns to the Library.
 */
export type AutoSaveStatus =
    | 'needs_topic'
    | 'saving'
    | 'saved'
    | 'retrying'
    | 'failed'
    | 'conflict'
    | 'archived'
    | 'deleted';

/** The editor content, as the page holds it. */
export interface AutoSaveSnapshot {
    topic: string;
    questions: PracticeQuestionForm[];
}

/** One save request, built from a snapshot plus the save markers. */
export interface AutoSaveRequest extends AutoSaveSnapshot {
    sessionId: number | null;
    version: number | null;
    /** Stable id for this editor, so a retried first save cannot create twice. */
    clientKey: string;
    /** Changes for every distinct save; retries of one save reuse it. */
    saveKey: string;
}

/** The outcome of a single save attempt. */
export type AutoSaveOutcome =
    | { outcome: 'saved'; session: PracticeSessionData; savedAt: string }
    | { outcome: 'invalid'; errors: Record<string, string> }
    | { outcome: 'conflict'; session: PracticeSessionData }
    | { outcome: 'archived'; message: string }
    | { outcome: 'deleted' }
    | { outcome: 'failed' };

/** Performs one save. Injected so the logic can be tested without a network. */
export type AutoSaveFunction = (
    request: AutoSaveRequest,
    options?: { keepalive?: boolean },
) => Promise<AutoSaveOutcome>;

export interface UseAutoSaveOptions {
    /** Reads the current editor content. */
    snapshot: () => AutoSaveSnapshot;
    /** Sends one save to the server. */
    save: AutoSaveFunction;
    /** Whether a topic has been chosen. */
    hasTopic: () => boolean;
    /** Whether any question holds content (used before a session exists). */
    hasContent: () => boolean;
    sessionId?: number | null;
    version?: number | null;
    /** The first save created the session; the page can update its address. */
    onSessionCreated?: (session: PracticeSessionData) => void;
    /** Called after every successful save. */
    onSaved?: (session: PracticeSessionData) => void;
    /** Replace the editor with a newer session after a conflict. */
    onLoadLatest?: (session: PracticeSessionData) => void;
    /** How long to wait after the last change before saving. */
    debounceMs?: number;
    /** Waits between automatic retries, capped at the last entry. */
    retryDelaysMs?: number[];
    /** Generates save keys. Overridable for deterministic tests. */
    newId?: () => string;
}

const DEFAULT_RETRY_DELAYS = [2000, 5000, 10000, 20000, 30000];

function createId(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

/**
 * Saving logic for the session editor, kept apart from the screen so it can be
 * reused by guided practice later and tested on its own.
 *
 * It waits for a pause in typing, runs one save at a time, queues changes made
 * during a save, retries with a growing wait, and refuses to overwrite a
 * session another tab changed. It never marks changes saved while newer edits
 * are still on screen.
 */
export function useAutoSave(options: UseAutoSaveOptions) {
    const debounceMs = options.debounceMs ?? 1500;
    const retryDelays = options.retryDelaysMs ?? DEFAULT_RETRY_DELAYS;
    const newId = options.newId ?? createId;

    const sessionId = ref<number | null>(options.sessionId ?? null);
    const version = ref<number | null>(options.version ?? null);
    const clientKey = newId();

    const status = ref<AutoSaveStatus>(
        sessionId.value === null && options.hasTopic() === false
            ? 'needs_topic'
            : 'saved',
    );
    const errors = ref<Record<string, string>>({});
    const conflict = ref<PracticeSessionData | null>(null);
    const archived = ref(false);
    const archivedMessage = ref('');
    const deleted = ref(false);
    const lastSavedAt = ref<number | null>(null);

    /** Bumped on every change, so a save knows if newer edits appeared. */
    const revision = ref(0);
    /** The revision the server has confirmed. */
    const savedRevision = ref(0);
    const dirty = computed(() => revision.value !== savedRevision.value);

    let pendingSaveKey = newId();
    let retryAttempt = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;
    let disposed = false;
    let applyingRemote = false;
    /** The save running right now, if any, so a caller can wait it out. */
    let currentSave: Promise<void> | null = null;

    function clearTimer(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    /** True when the current content is worth sending. */
    function canSave(): boolean {
        if (!options.hasTopic()) {
            return false;
        }

        // Once a session exists, clearing every field is still a change worth
        // saving. Before that, there must be content to start a session with.
        return sessionId.value !== null || options.hasContent();
    }

    function schedule(delay: number): void {
        clearTimer();

        // A session archived elsewhere stays read-only until it is restored;
        // nothing is queued in the meantime. A deleted session is gone for
        // good, so there is nothing left to save into.
        if (archived.value) {
            status.value = 'archived';

            return;
        }

        if (deleted.value) {
            status.value = 'deleted';

            return;
        }

        timer = setTimeout(() => {
            timer = null;
            void attemptSave();
        }, delay);

        if (!options.hasTopic()) {
            status.value = 'needs_topic';
        } else if (canSave()) {
            status.value = 'saving';
        } else {
            // A topic is set, but there is nothing worth saving yet.
            status.value = 'saved';
        }
    }

    function scheduleRetry(): void {
        const delay =
            retryDelays[Math.min(retryAttempt, retryDelays.length - 1)];
        retryAttempt += 1;
        status.value = 'retrying';
        clearTimer();
        timer = setTimeout(() => {
            timer = null;
            void attemptSave();
        }, delay);
    }

    /**
     * Runs one attempt and tracks it, so `saveNow` can wait out a save that is
     * already on its way instead of racing it.
     *
     * The returned promise never rejects: the outcome is already recorded in
     * `status`, `conflict` and `errors`, and callers only need to know when the
     * attempt has finished.
     */
    function attemptSave(): Promise<void> {
        const attempt = performSave();
        const settled = attempt.catch(() => undefined);

        currentSave = settled;

        void settled.then(() => {
            if (currentSave === settled) {
                currentSave = null;
            }
        });

        return settled;
    }

    async function performSave(): Promise<void> {
        if (
            disposed ||
            archived.value ||
            deleted.value ||
            inFlight ||
            conflict.value
        ) {
            return;
        }

        if (!options.hasTopic()) {
            status.value = 'needs_topic';

            return;
        }

        if (!canSave()) {
            // Nothing to send yet; treat the current edits as settled.
            savedRevision.value = revision.value;
            status.value = 'saved';

            return;
        }

        const sentRevision = revision.value;
        const saveKey = pendingSaveKey;
        const request: AutoSaveRequest = {
            ...options.snapshot(),
            sessionId: sessionId.value,
            version: version.value,
            clientKey,
            saveKey,
        };

        inFlight = true;
        status.value = 'saving';

        let result: AutoSaveOutcome;

        try {
            result = await options.save(request);
        } catch {
            result = { outcome: 'failed' };
        } finally {
            inFlight = false;
        }

        if (disposed) {
            return;
        }

        applyResult(result, sentRevision);
    }

    function applyResult(result: AutoSaveOutcome, sentRevision: number): void {
        if (result.outcome === 'saved') {
            const created = sessionId.value === null;
            sessionId.value = result.session.id;
            version.value = result.session.version;
            lastSavedAt.value = Date.now();
            retryAttempt = 0;
            errors.value = {};

            if (created) {
                options.onSessionCreated?.(result.session);
            }

            options.onSaved?.(result.session);

            if (sentRevision === revision.value) {
                savedRevision.value = revision.value;
                status.value = 'saved';
                pendingSaveKey = newId();
            } else {
                // The learner kept typing during the save; save the newest next.
                pendingSaveKey = newId();
                schedule(0);
            }

            return;
        }

        if (result.outcome === 'invalid') {
            errors.value = result.errors;
            status.value = 'failed';

            return;
        }

        if (result.outcome === 'conflict') {
            conflict.value = result.session;
            status.value = 'conflict';

            return;
        }

        if (result.outcome === 'archived') {
            archived.value = true;
            archivedMessage.value = result.message;
            status.value = 'archived';

            return;
        }

        if (result.outcome === 'deleted') {
            deleted.value = true;
            status.value = 'deleted';

            return;
        }

        scheduleRetry();
    }

    /** Try again now, usually from the Retry button. */
    function retry(): void {
        if (disposed || archived.value || deleted.value || conflict.value) {
            return;
        }

        retryAttempt = 0;
        clearTimer();
        void attemptSave();
    }

    /** Replace the editor with the version saved elsewhere. */
    function loadLatest(): void {
        const latest = conflict.value;

        if (latest === null) {
            return;
        }

        clearTimer();
        applyingRemote = true;
        sessionId.value = latest.id;
        version.value = latest.version;
        conflict.value = null;
        errors.value = {};
        savedRevision.value = revision.value;
        status.value = 'saved';
        pendingSaveKey = newId();

        options.onLoadLatest?.(latest);

        // The page's fields have just been replaced; give that change a flush to
        // pass before edits count as the learner's again.
        void nextTick(() => {
            applyingRemote = false;
        });
    }

    /** Keep the on-screen content and save it over the other version. */
    function keepMine(): void {
        const latest = conflict.value;

        if (latest === null) {
            return;
        }

        clearTimer();
        version.value = latest.version;
        conflict.value = null;
        errors.value = {};
        retryAttempt = 0;
        pendingSaveKey = newId();
        void attemptSave();
    }

    /** Best-effort save for when the page is being hidden or left. */
    function flush(): void {
        if (
            disposed ||
            archived.value ||
            deleted.value ||
            !dirty.value ||
            conflict.value ||
            !canSave()
        ) {
            return;
        }

        const request: AutoSaveRequest = {
            ...options.snapshot(),
            sessionId: sessionId.value,
            version: version.value,
            clientKey,
            saveKey: pendingSaveKey,
        };

        void options.save(request, { keepalive: true }).catch(() => undefined);
    }

    /**
     * Saves right now instead of waiting out the pause: it drops the pending
     * timer, waits out a save already on its way, and sends whatever is still
     * unsaved. It repeats while the screen keeps moving ahead of the server, so
     * what gets confirmed is always the newest content.
     *
     * Returns false when the content could not be confirmed saved — no topic
     * yet, a conflict, or a failed save — so a caller can stop rather than act
     * on work the server has not seen.
     */
    async function saveNow(): Promise<boolean> {
        if (
            disposed ||
            archived.value ||
            deleted.value ||
            !options.hasTopic()
        ) {
            return false;
        }

        clearTimer();

        while (
            !disposed &&
            !archived.value &&
            !deleted.value &&
            !conflict.value &&
            dirty.value
        ) {
            while (
                currentSave !== null &&
                !disposed &&
                !archived.value &&
                !deleted.value &&
                !conflict.value
            ) {
                await currentSave;
            }

            if (disposed || archived.value || deleted.value || conflict.value) {
                return false;
            }

            if (dirty.value) {
                await attemptSave();
            }
        }

        return (
            !disposed && !archived.value && !deleted.value && !conflict.value
        );
    }

    /**
     * Save again after the learner restored a session that was archived
     * elsewhere. Their text stayed on screen while saving was refused.
     */
    function resumeAfterRestore(): void {
        if (!archived.value) {
            return;
        }

        archived.value = false;
        archivedMessage.value = '';
        retryAttempt = 0;
        clearTimer();
        pendingSaveKey = newId();
        status.value = 'saved';

        if (dirty.value) {
            void attemptSave();
        }
    }

    watch(
        () => options.snapshot(),
        () => {
            if (applyingRemote) {
                return;
            }

            revision.value += 1;
            errors.value = {};

            if (conflict.value) {
                // Wait for the learner to choose before saving over anything.
                return;
            }

            schedule(debounceMs);
        },
        { deep: true },
    );

    function onBeforeUnload(event: BeforeUnloadEvent): void {
        if (!dirty.value) {
            return;
        }

        // The browser shows its own standard "leave site?" wording.
        event.preventDefault();
        event.returnValue = '';
    }

    function onPageHide(): void {
        flush();
    }

    function onVisibilityChange(): void {
        if (document.visibilityState === 'hidden') {
            flush();
        }
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('pagehide', onPageHide);
        document.addEventListener('visibilitychange', onVisibilityChange);
    }

    function dispose(): void {
        disposed = true;
        clearTimer();

        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('pagehide', onPageHide);
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
        }
    }

    if (getCurrentScope()) {
        onScopeDispose(dispose);
    }

    return {
        sessionId,
        version,
        status,
        errors,
        conflict,
        archived,
        archivedMessage,
        deleted,
        lastSavedAt,
        hasUnsavedChanges: dirty,
        retry,
        loadLatest,
        keepMine,
        resumeAfterRestore,
        flush,
        saveNow,
        dispose,
    };
}
