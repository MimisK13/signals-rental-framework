import assert from 'node:assert/strict';

import { createTreeDragController } from '../../resources/js/experimental/quotation-editor/tree-drag.js';

let domMutationCount = 0;
let rectReadCount = 0;

class FakeElement {
    constructor(tagName) {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.parentNode = null;
        this.className = '';
        this.dataset = {};
        this.style = {};
        this.textContent = '';
        this.rect = { top: 0, bottom: 0, height: 0 };
    }

    appendChild(child) {
        child.detach();
        child.parentNode = this;
        this.children.push(child);
        domMutationCount++;

        return child;
    }

    insertBefore(child, reference) {
        child.detach();
        const index = this.children.indexOf(reference);

        if (index === -1) {
            return this.appendChild(child);
        }

        child.parentNode = this;
        this.children.splice(index, 0, child);
        domMutationCount++;

        return child;
    }

    detach() {
        if (!this.parentNode) {
            return;
        }

        const index = this.parentNode.children.indexOf(this);

        if (index !== -1) {
            this.parentNode.children.splice(index, 1);
        }

        this.parentNode = null;
    }

    remove() {
        this.detach();
        domMutationCount++;
    }

    append(...children) {
        for (const child of children) {
            this.appendChild(child);
        }
    }

    querySelectorAll(selector) {
        if (selector !== 'tr.lf-row') {
            return [];
        }

        return this.children.filter((child) => child.tagName === 'TR' && child.className.split(' ').includes('lf-row'));
    }

    querySelector(selector) {
        const match = selector.match(/^tr\[data-id="(-?\d+)"\]$/);

        if (!match) {
            return null;
        }

        return this.children.find((child) => Number(child.dataset.id) === Number(match[1])) ?? null;
    }

    getBoundingClientRect() {
        rectReadCount++;

        return this.rect;
    }
}

const listeners = new Map();
const animationFrames = new Map();
let nextAnimationFrameId = 1;

globalThis.document = {
    createElement: (tagName) => new FakeElement(tagName),
};

globalThis.window = {
    addEventListener(type, listener) {
        const handlers = listeners.get(type) ?? new Set();
        handlers.add(listener);
        listeners.set(type, handlers);
    },
    removeEventListener(type, listener) {
        listeners.get(type)?.delete(listener);
    },
    requestAnimationFrame(callback) {
        const id = nextAnimationFrameId++;
        animationFrames.set(id, callback);

        return id;
    },
    cancelAnimationFrame(id) {
        animationFrames.delete(id);
    },
};

function dispatch(type, event = {}) {
    return Promise.all([...listeners.get(type) ?? []].map((listener) => listener(event)));
}

function flushAnimationFrames() {
    const pending = [...animationFrames.values()];
    animationFrames.clear();

    for (const callback of pending) {
        callback();
    }
}

function tableRow(id, top) {
    const row = new FakeElement('tr');
    row.className = 'lf-row';
    row.dataset.id = String(id);
    row.rect = { top, bottom: top + 20, height: 20 };

    return row;
}

const rows = [
    { id: 1, depth: 1, item_type: 'product', name: 'One' },
    { id: 2, depth: 1, item_type: 'product', name: 'Two' },
    { id: 3, depth: 1, item_type: 'product', name: 'Three' },
];
const tbody = new FakeElement('tbody');
tbody.append(tableRow(1, 0), tableRow(2, 20), tableRow(3, 40));
const ghost = new FakeElement('div');
let droppedRows = null;

domMutationCount = 0;

const controller = createTreeDragController({
    getRows: () => rows,
    getEditable: () => true,
    getTbody: () => tbody,
    getGhost: () => ghost,
    setDragState: () => {},
    onDrop: ({ rows: nextRows }) => {
        droppedRows = nextRows;
    },
});

controller.onHandleDown({
    button: 0,
    clientX: 10,
    clientY: 50,
    pointerId: 1,
    preventDefault() {},
}, 3);

assert.equal(rectReadCount, 2, 'drag start measures each non-dragged row once');

await dispatch('pointermove', { clientX: 10, clientY: 5 });
await dispatch('pointermove', { clientX: 11, clientY: 5 });
await dispatch('pointermove', { clientX: 12, clientY: 5 });

assert.equal(animationFrames.size, 1, 'pointer moves coalesce into one animation frame');
assert.equal(rectReadCount, 2, 'pointer moves reuse cached geometry');

flushAnimationFrames();

const placeholder = tbody.children.find((child) => child.className.includes('lf-placeholder'));
const mutationsAfterFirstTarget = domMutationCount;

assert.ok(placeholder, 'the drag renders one placeholder');
assert.equal(rectReadCount, 2, 'processing a pointer frame does not remeasure rows');

await dispatch('pointermove', { clientX: 12, clientY: 5 });
flushAnimationFrames();

assert.equal(
    tbody.children.find((child) => child.className.includes('lf-placeholder')),
    placeholder,
    'the same placeholder element is reused',
);
assert.equal(domMutationCount, mutationsAfterFirstTarget, 'an unchanged target does not move the placeholder');

await dispatch('scroll');
flushAnimationFrames();

assert.equal(rectReadCount, 4, 'scroll invalidates and refreshes cached row geometry once');

await dispatch('pointermove', { clientX: 10, clientY: 25 });
assert.equal(animationFrames.size, 1, 'the final pointer move is pending until the next frame');

await dispatch('pointerup');

assert.deepEqual(droppedRows.map((row) => row.id), [1, 3, 2], 'pointerup flushes the final target before dropping');
assert.equal(animationFrames.size, 0, 'pointerup cancels the pending animation frame');
assert.equal(tbody.children.includes(placeholder), false, 'cleanup removes the placeholder');
assert.equal(listeners.get('pointermove')?.size ?? 0, 0, 'cleanup removes pointer listeners');
assert.equal(listeners.get('scroll')?.size ?? 0, 0, 'cleanup removes geometry invalidation listeners');
const dragRectReadCount = rectReadCount;

window.__lfExperimentalQuotationEditorConfig = {};
window.__lfExperimentalQuotationEditorSeed = {};
globalThis.Livewire = { dispatch() {} };

const { default: createEditor } = await import('../../resources/js/experimental/quotation-editor/editor.js');
const persistenceRows = [
    { id: 11, depth: 1, item_type: 'product', name: 'One' },
    { id: 12, depth: 1, item_type: 'product', name: 'Two' },
    { id: 13, depth: 1, item_type: 'product', name: 'Three' },
];
const persistenceTbody = new FakeElement('tbody');
persistenceTbody.append(tableRow(11, 0), tableRow(12, 20), tableRow(13, 40));
const pendingTimers = new Map();
let nextTimerId = 1;
const originalSetTimeout = globalThis.setTimeout;
const originalClearTimeout = globalThis.clearTimeout;

globalThis.setTimeout = (callback) => {
    const id = nextTimerId++;
    pendingTimers.set(id, callback);

    return id;
};
globalThis.clearTimeout = (id) => pendingTimers.delete(id);

const persistenceEditor = createEditor({ oppId: 99 });
persistenceEditor.editable = true;
persistenceEditor.rows = persistenceRows;
persistenceEditor.baseRevision = '1';
persistenceEditor.$refs = {
    tbody: persistenceTbody,
    ghost: new FakeElement('div'),
};
persistenceEditor.$nextTick = (callback) => callback();

let persistedNodes = null;
persistenceEditor.$wire = {
    async treeRevisionToken() {
        return '1';
    },
    async persistTree(nodes) {
        persistedNodes = nodes;

        return { stale: false, revision: '2' };
    },
    async pullTree() {
        return {
            changed: true,
            stale: false,
            revision: '2',
            tree: persistenceEditor.rows,
            conflicts: [],
            collapsed_section_ids: [],
            charge_total: 0,
            deal_total: null,
            has_deal_price: false,
        };
    },
};

await persistenceEditor.boot();
persistenceEditor.onHandleDown({
    button: 0,
    clientX: 10,
    clientY: 50,
    pointerId: 2,
    preventDefault() {},
}, 13);
await dispatch('pointermove', { clientX: 10, clientY: 5 });
flushAnimationFrames();
await dispatch('pointerup');

assert.deepEqual(
    persistedNodes?.map((node) => node.id),
    [13, 11, 12],
    'a completed drop persists its ordering before a navigation can destroy the editor',
);

persistenceEditor.destroy();
globalThis.setTimeout = originalSetTimeout;
globalThis.clearTimeout = originalClearTimeout;

process.stdout.write(JSON.stringify({
    passed: true,
    rectReadCount: dragRectReadCount,
    droppedOrder: droppedRows.map((row) => row.id),
    persistedOrder: persistedNodes.map((node) => node.id),
}));
