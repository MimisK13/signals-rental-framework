/**
 * Replaces Livewire's native `confirm()` dialog for `wire:confirm` with the
 * global Flux confirmation modal (`signals-confirm`). Call sites keep
 * `wire:confirm="message"` unchanged; this hook overwrites `el.__livewire_confirm`
 * after Livewire's built-in directive runs.
 */

const DANGER_MESSAGE_PATTERN = /\b(delete|void|dissolve|remove|revoke|reset|cancel|orphan|destroy|discard|uninstall|inaccessible|will be lost|removed\.)\b/i;

function normalizeConfirmMessage(expression) {
    let message = expression ?? '';
    message = message.replaceAll('\\n', '\n');

    if (message === '') {
        message = 'Are you sure?';
    }

    return message;
}

function isDangerConfirm(message, directive) {
    if (directive.modifiers.includes('danger')) {
        return true;
    }

    if (directive.modifiers.includes('safe')) {
        return false;
    }

    return DANGER_MESSAGE_PATTERN.test(message);
}

function registerConfirmApi() {
    window.signals = window.signals || {};

    /** @type {{ resolve: (value: boolean) => void } | null} */
    let pending = null;

    window.signals.confirm = {
        /**
         * @param {string} message
         * @param {{ danger?: boolean }} [options]
         * @returns {Promise<boolean>}
         */
        show(message, { danger = false } = {}) {
            if (pending) {
                pending.resolve(false);
            }

            return new Promise((resolve) => {
                pending = { resolve };

                window.dispatchEvent(new CustomEvent('signals:confirm:open', {
                    detail: { message, danger },
                }));
            });
        },

        /** @param {boolean} confirmed */
        resolve(confirmed) {
            if (! pending) {
                return;
            }

            pending.resolve(confirmed);
            pending = null;
        },
    };
}

function registerConfirmModalAlpine() {
    const register = () => {
        Alpine.data('signalsConfirmModal', () => ({
            message: '',
            danger: false,
            settled: false,
            _onOpen: null,

            init() {
                this._onOpen = (event) => {
                    this.message = event.detail?.message ?? 'Are you sure?';
                    this.danger = Boolean(event.detail?.danger);
                    this.settled = false;

                    this.$nextTick(() => {
                        if (window.Flux?.modal) {
                            window.Flux.modal('signals-confirm').show();
                        }

                        this.$nextTick(() => {
                            this.$refs.confirmButton?.focus?.();
                        });
                    });
                };

                window.addEventListener('signals:confirm:open', this._onOpen);
            },

            destroy() {
                if (this._onOpen) {
                    window.removeEventListener('signals:confirm:open', this._onOpen);
                }
            },

            closeModal() {
                if (window.Flux?.modal) {
                    window.Flux.modal('signals-confirm').close();
                }
            },

            confirm() {
                if (this.settled) {
                    return;
                }

                this.settled = true;
                this.closeModal();
                window.signals?.confirm?.resolve(true);
            },

            cancel() {
                if (this.settled) {
                    return;
                }

                this.settled = true;
                this.closeModal();
                window.signals?.confirm?.resolve(false);
            },
        }));
    };

    if (window.Alpine) {
        register();
    } else {
        document.addEventListener('alpine:init', register);
    }
}

function registerLivewireConfirmHook() {
    document.addEventListener('livewire:init', () => {
        Livewire.hook('directive.init', ({ el, directive }) => {
            if (directive.value !== 'confirm') {
                return;
            }

            const message = normalizeConfirmMessage(directive.expression);
            const shouldPrompt = directive.modifiers.includes('prompt');
            const danger = isDangerConfirm(message, directive);

            el.__livewire_confirm = (action, instead) => {
                if (shouldPrompt) {
                    const [question, expected] = message.split('|');

                    if (! expected) {
                        console.warn('Livewire: Must provide expectation with wire:confirm.prompt');
                        instead();

                        return;
                    }

                    const input = prompt(question);

                    if (input === expected) {
                        action();
                    } else {
                        instead();
                    }

                    return;
                }

                window.signals.confirm.show(message, { danger }).then((confirmed) => {
                    if (confirmed) {
                        action();
                    } else {
                        instead();
                    }
                });
            };
        });
    });
}

export default function registerLivewireConfirm() {
    registerConfirmApi();
    registerConfirmModalAlpine();
    registerLivewireConfirmHook();
}
