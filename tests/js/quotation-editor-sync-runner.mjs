import assert from 'node:assert/strict';

globalThis.window = {
    __lfQuotationEditorConfig: {},
    __lfQuotationEditorSeed: {},
};
globalThis.Livewire = { dispatch() {} };

const { default: createEditor } = await import('../../resources/js/quotations/editor.js');

const editor = createEditor({ quotationId: 1 });
editor.rows = [{ id: 10, name: 'Camera', path: '0001' }];
editor.baseRevision = '1';

let releaseFirstPull;
let calls = 0;
let inFlight = 0;
let maximumInFlight = 0;
const uploadedRowCounts = [];

const firstPullBlocked = new Promise((resolve) => {
    releaseFirstPull = resolve;
});

editor.$wire = {
    async pullTree(baseRevision, localRows) {
        calls++;
        inFlight++;
        maximumInFlight = Math.max(maximumInFlight, inFlight);
        uploadedRowCounts.push(localRows.length);

        if (calls === 1) {
            await firstPullBlocked;
        }

        inFlight--;

        return {
            changed: false,
            revision: String(calls + 1),
            charge_total: 1000,
            deal_total: null,
            has_deal_price: false,
        };
    },
};

const first = editor.pullFromServer(true, { reason: 'first' });
await Promise.resolve();
const second = editor.pullFromServer(true, { reason: 'second' });
const third = editor.pullFromServer(false, { reason: 'third' });

releaseFirstPull();
await Promise.all([first, second, third]);

assert.equal(calls, 2, 'overlapping refreshes should coalesce into one follow-up pull');
assert.equal(maximumInFlight, 1, 'pulls should never overlap');
assert.deepEqual(editor.rows, [{ id: 10, name: 'Camera', path: '0001' }]);
assert.equal(editor.baseRevision, '3');
assert.deepEqual(uploadedRowCounts, [0, 0]);

const batchEditor = createEditor({ quotationId: 2 });
batchEditor.rows = [{ id: 10, quantity: '3' }];

let fieldBatchCalls = 0;
let submittedMutations = [];

batchEditor.$wire = {
    async applyFieldMutations(mutations) {
        fieldBatchCalls++;
        submittedMutations = mutations;

        return {
            revision: '4',
            charge_total: 3000,
            deal_total: null,
            has_deal_price: false,
        };
    },
};

const fieldStateWasSynced = await batchEditor.applyBatchToServer([
    { kind: 'field', id: 10, field: 'quantity', value: '2' },
    { kind: 'field', id: 10, field: 'quantity', value: '3' },
]);

assert.equal(fieldBatchCalls, 1);
assert.equal(submittedMutations.length, 1);
assert.deepEqual(submittedMutations[0], {
    id: 10,
    field: 'quantity',
    value: '3',
});
assert.equal(fieldStateWasSynced, true);
assert.equal(batchEditor.baseRevision, '4');

process.stdout.write(JSON.stringify({
    calls,
    maximumInFlight,
    rows: editor.rows.length,
    uploadedRowCounts,
    fieldBatchCalls,
    submittedFieldMutations: submittedMutations.length,
}));
