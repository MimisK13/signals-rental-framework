import { EditorState, Compartment } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language';

const TAB_PROPERTIES = {
    body: 'bodyHtml',
    header: 'headerHtml',
    footer: 'footerHtml',
    styles: 'stylesCss',
};

const DEBOUNCE_MS = 500;

const languageCompartment = new Compartment();

function languageForTab(tab) {
    return tab === 'styles' ? css() : html();
}

function lightTheme() {
    return EditorView.theme({
        '&': {
            height: '100%',
            fontSize: '11px',
            fontFamily: 'var(--font-mono, ui-monospace, monospace)',
            backgroundColor: 'var(--base, #ffffff)',
            color: 'var(--text-primary, #111827)',
        },
        '.cm-scroller': {
            overflow: 'auto',
            fontFamily: 'inherit',
        },
        '.cm-content': {
            padding: '12px',
            caretColor: 'var(--text-primary, #111827)',
        },
        '.cm-gutters': {
            backgroundColor: 'var(--card-bg, #f9fafb)',
            border: 'none',
            borderRight: '1px solid var(--card-border, #e5e7eb)',
            minWidth: '48px',
        },
        '.cm-lineNumbers .cm-gutterElement': {
            textAlign: 'right',
            paddingRight: '12px',
            fontFamily: 'var(--font-mono, ui-monospace, monospace)',
            fontSize: '10px',
            lineHeight: '1.7',
            color: 'var(--text-muted, #6b7280)',
        },
        '.cm-activeLineGutter': {
            backgroundColor: 'rgba(0, 0, 0, 0.05)',
            color: 'var(--text-primary, #111827)',
            fontWeight: '600',
        },
        '.cm-activeLine': {
            backgroundColor: 'rgba(0, 0, 0, 0.02)',
            boxShadow: 'inset 0 -1px var(--card-border, rgba(0, 0, 0, 0.06))',
        },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground': {
            backgroundColor: 'rgba(59, 130, 246, 0.25) !important',
        },
    }, { dark: false });
}

function debounce(fn, wait) {
    let timer = null;

    return (...args) => {
        if (timer !== null) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            timer = null;
            fn(...args);
        }, wait);
    };
}

export default function createDocumentTemplateEditor(config) {
    const wire = config.wire;
    const contents = { ...config.contents };
    let view = null;
    let syncingFromWire = false;
    let pendingWireSync = null;
    let trackedTab = null;

    const pushToWire = debounce((tab, value) => {
        if (syncingFromWire) {
            return;
        }

        const property = TAB_PROPERTIES[tab];
        if (!property || !wire) {
            return;
        }

        wire.set(property, value);
    }, DEBOUNCE_MS);

    function updateListener() {
        return EditorView.updateListener.of((update) => {
            if (!update.docChanged || syncingFromWire || trackedTab === null) {
                return;
            }

            contents[trackedTab] = update.state.doc.toString();
            pushToWire(trackedTab, contents[trackedTab]);
        });
    }

    function switchTab(nextTab) {
        if (!view || nextTab === trackedTab) {
            trackedTab = nextTab;

            return;
        }

        if (trackedTab !== null) {
            contents[trackedTab] = view.state.doc.toString();
        }

        trackedTab = nextTab;

        syncingFromWire = true;
        view.dispatch({
            changes: {
                from: 0,
                to: view.state.doc.length,
                insert: contents[nextTab] ?? '',
            },
            effects: languageCompartment.reconfigure(languageForTab(nextTab)),
        });
        syncingFromWire = false;
    }

    function syncFromWire(nextContents) {
        if (!view) {
            Object.assign(contents, nextContents);

            return;
        }

        if (trackedTab !== null) {
            contents[trackedTab] = view.state.doc.toString();
        }

        Object.assign(contents, nextContents);

        syncingFromWire = true;
        view.dispatch({
            changes: {
                from: 0,
                to: view.state.doc.length,
                insert: contents[trackedTab ?? 'body'] ?? '',
            },
        });
        syncingFromWire = false;
    }

    return {
        cmReady: false,

        init() {
            const mount = () => {
                const host = this.$refs.cmHost;
                if (!host || view !== null) {
                    if (!host && view === null) {
                        console.warn('[dt-editor] CodeMirror host ref missing — textarea fallback active');
                    }

                    return;
                }

                try {
                    trackedTab = this.activeTab ?? 'body';
                    view = new EditorView({
                        state: EditorState.create({
                            doc: contents[trackedTab] ?? '',
                            extensions: [
                                lineNumbers(),
                                highlightActiveLine(),
                                highlightActiveLineGutter(),
                                history(),
                                keymap.of([...defaultKeymap, ...historyKeymap]),
                                languageCompartment.of(languageForTab(trackedTab)),
                                syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                                lightTheme(),
                                updateListener(),
                                EditorView.lineWrapping,
                            ],
                        }),
                        parent: host,
                    });
                    this.cmReady = true;

                    if (typeof Livewire !== 'undefined') {
                        Livewire.on('document-template-editor-sync', () => {
                            if (pendingWireSync) {
                                clearTimeout(pendingWireSync);
                            }

                            pendingWireSync = setTimeout(() => {
                                pendingWireSync = null;
                                syncFromWire({
                                    body: wire.bodyHtml,
                                    header: wire.headerHtml,
                                    footer: wire.footerHtml,
                                    styles: wire.stylesCss,
                                });
                            }, 0);
                        });
                    }

                    this.$watch('activeTab', (nextTab) => {
                        switchTab(nextTab);
                    });
                } catch (error) {
                    console.error('[dt-editor] CodeMirror mount failed', error);
                    this.cmReady = false;
                }
            };

            if (this.$refs.cmHost) {
                mount();

                return;
            }

            this.$nextTick(() => mount());
        },

        destroy() {
            if (pendingWireSync) {
                clearTimeout(pendingWireSync);
            }

            view?.destroy();
            view = null;
        },

        insertAtCursor(snippet) {
            if (!snippet) {
                return;
            }

            if (view) {
                const { from, to } = view.state.selection.main;
                view.dispatch({
                    changes: { from, to, insert: snippet },
                    selection: { anchor: from + snippet.length },
                });
                view.focus();

                return;
            }

            console.warn('[dt-editor] CodeMirror view unavailable — falling back to server insertSnippet');
            wire?.call('insertSnippet', snippet);
        },
    };
}
