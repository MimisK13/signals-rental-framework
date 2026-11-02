/**
 * Canonical local-first quotation line-item editor (Alpine data factory).
 * Ported from editor-local-first prototype with real $wire action mapping.
 */
import {
    hasStructuralPending,
    pendingLocalIdsFromQueue,
    reconcileLocalTree,
} from '../line-item-tree-reconcile.js';
import {
    mutationsToRequeue,
    orderFlushBatch,
    resolveServerItemId,
    rowsEligibleForPersistTree,
    shouldScheduleFlushRetry,
} from '../line-item-mutation-flush.js';
import { normalizeRevision } from '../line-item-revision.js';
import { createTreeDragController } from './tree-drag.js';

const isDevelopmentBuild = import.meta.env?.DEV === true;

function logStructuralAdd(message, details = {}) {
    if (isDevelopmentBuild) {
        console.info(`[quotation-editor] ${message}`, details);
    }
}

export function sameRowId(a, b) {
    if (a == null || b == null) {
        return false;
    }

    if (a === b) {
        return true;
    }

    const left = Number(a);
    const right = Number(b);

    return Number.isFinite(left) && Number.isFinite(right) && left === right;
}

function resolveEditorConfig(cfg) {
    const quotationId = cfg?.quotationId;
    const frozen =
        typeof window !== 'undefined' && quotationId != null
            ? (window.__lfQuotationEditorConfig || {})[quotationId] || {}
            : {};

    return { ...frozen, ...cfg };
}

export default function createQuotationLineItemsEditor(cfg) {
    const resolved = resolveEditorConfig(cfg);

    return {
        quotationId: resolved.quotationId,
        csrfToken: resolved.csrfToken || '',
        editable: !!resolved.editable,
        fieldsEditable:
            resolved.fieldsEditable !== undefined ? !!resolved.fieldsEditable : !!resolved.editable,
        pricingFrozen: !!resolved.pricingFrozen,
        priceLocked: !!resolved.priceLocked,
        canManagePriceLock: !!resolved.canManagePriceLock,
        catalogue: resolved.catalogue || [],
        currencySymbol: resolved.currencySymbol || '£',
        echoChannel: resolved.echoChannel || '',
        destinations: resolved.destinations || [],
        sectionOptions: resolved.sectionOptions || [],
        serverChargeTotalMinor: Number(resolved.serverChargeTotalMinor) || 0,
        serverDealTotalMinor:
            resolved.serverDealTotalMinor != null ? Number(resolved.serverDealTotalMinor) : null,
        dealPriceInput: resolved.dealTotalRaw || '',
        dealPriceMode: resolved.dealPriceMode || 'flat_headline',
        hasDealPrice: !!resolved.hasDealPrice,
        quickAddSelection: null,
        quickAddQuery: '',
        mutationPending: false,
        structuralMutationPending: false,
        sectionStatePending: false,
        pricingRecalculating: false,
        collapsedSectionIds: (resolved.collapsedSectionIds || []).map((id) => Number(id)),

        get seedPayload() {
            return (window.__lfQuotationEditorSeed || {})[this.quotationId] || { tree: [], revision: 0 };
        },

        get seedRows() {
            return this.seedPayload.tree || [];
        },

        rows: [],
        baseRevision: '0',
        conflicts: {},
        openMenu: null,
        menuRow: null,
        confirmDeleteId: null,
        menuPos: { top: 0, right: 0 },
        groupPanelOpen: false,
        groupPanelRowId: null,
        groupPanelRowName: '',
        groupPanelOptions: [],
        groupPanelPending: false,
        _menuAnchor: null,
        _menuOutsideSuppressedUntil: null,

        queue: [],
        syncState: 'idle',
        _flushTimer: null,
        _idleHandle: null,
        _flushing: false,
        _pendingFlush: false,
        _deferredPull: null,
        _queuedPull: null,
        _pullPromise: null,
        _booted: false,
        _destroyed: false,

        chargePopover: null,
        _chargePopoverTimer: null,

        picker: {
            open: false,
            target: null,
            isQuickAdd: false,
            results: [],
            highlight: 0,
            loading: false,
            localCount: 0,
            serverCount: 0,
            query: '',
            seq: 0,
            quantity: 1,
            mode: 'add',
            substituteItemId: null,
        },
        quickAddQty: '',
        quickAddQtyHint: '',
        _serverTimer: null,
        _searchController: null,
        _broadcast: null,
        _echoChannel: null,
        _onScroll: null,
        _onResize: null,
        dragController: null,
        dragId: null,
        nestDropGroupId: null,

        _tempSeq: 0,
        _quickAddFocused: false,

        async boot() {
            if (this._booted) {
                return;
            }

            this._destroyed = false;

            this.dragController = createTreeDragController({
                getRows: () => this.rows,
                getEditable: () => this.editable,
                getTbody: () => this.$refs.tbody,
                getGhost: () => this.$refs.ghost,
                setDragState: ({ dragId, nestDropGroupId }) => {
                    this.dragId = dragId;
                    this.nestDropGroupId = nestDropGroupId;
                },
                onDrop: async ({ rows }) => {
                    this.rows = this.recomputeFlags(rows);
                    this.enqueue({ kind: 'persistTree' });
                    clearTimeout(this._flushTimer);
                    this._flushTimer = null;
                    await this.flush();
                },
                onInvalidDrop: async () => {
                    this.recomputeFlags(this.rows);
                },
            });

            if (this.rows.length) {
                this._booted = true;
                this.focusQuickAddOnce();

                return;
            }

            this._booted = true;
            this.baseRevision = normalizeRevision(this.seedPayload.revision);
            this.initBroadcast();
            this.initEcho();

            this.replaceRows(this.seedRows);
            this.applyDefaultCollapse(this.rows);
            this.applyCollapsedSectionState(this.collapsedSectionIds);
            this.setSync('synced');
            void this.initProductSearch();

            this.focusQuickAddOnce();
        },

        focusQuickAddOnce() {
            if (this._quickAddFocused || !this.editable) {
                return;
            }

            this._quickAddFocused = true;

            this.$nextTick(() => {
                this.$refs.quickAddInput?.focus();
            });
        },

        idsMatch(a, b) {
            return sameRowId(a, b);
        },

        findRow(id) {
            return this.rows.find((row) => sameRowId(row.id, id)) ?? null;
        },

        findRowIndex(id) {
            return this.rows.findIndex((row) => sameRowId(row.id, id));
        },

        markRowJustAdded(id) {
            const row = this.findRow(id);

            if (!row) {
                return;
            }

            row._justAdded = true;
            setTimeout(() => {
                row._justAdded = false;
            }, 2000);
        },

        markRowsAddedSince(beforeIds) {
            for (const row of this.rows) {
                if (!beforeIds.has(Number(row.id))) {
                    this.markRowJustAdded(row.id);
                }
            }
        },

        enrichRowsWithServerMetadata(rows, serverRows) {
            if (!serverRows?.length) {
                return rows;
            }

            const serverById = Object.fromEntries(serverRows.map((row) => [Number(row.id), row]));
            const serverFields = [
                'product_url',
                'product_id',
                'has_shortage',
                'status_label',
                'availability_status',
                'availability_url',
                'charge_breakdown',
                'charge_total',
                'charge_total_display',
                'type_label',
                'planning',
            ];

            return rows.map((row) => {
                const server = serverById[Number(row.id)];

                if (!server) {
                    return row;
                }

                const enriched = { ...row };

                for (const field of serverFields) {
                    if (server[field] !== undefined && server[field] !== null) {
                        enriched[field] = server[field];
                    }
                }

                return enriched;
            });
        },

        async initProductSearch() {
            if (!window.signals?.productSearch?.createProductSearch) {
                return;
            }

            if (!this.catalogue.length) {
                try {
                    this.catalogue = await this.$wire.catalogueIndex();
                } catch (error) {
                    console.error('quotation editor catalogue load failed', error);
                    this.catalogue = [];
                }
            }

            if (this._destroyed) {
                return;
            }

            this._searchController = window.signals.productSearch.createProductSearch({
                catalogue: this.catalogue,
                serverSearch: (term) => this.$wire.searchProducts(term),
                limit: 12,
            });

            // Store named refs so destroy() can remove these (capture-phase scroll +
            // resize) listeners — otherwise they accumulate on every wire:navigate.
            this._onScroll = () => {
                this.positionPicker();
                this.positionRowMenu();
            };
            this._onResize = () => {
                this.positionPicker();
                this.positionRowMenu();
            };

            window.addEventListener('scroll', this._onScroll, true);
            window.addEventListener('resize', this._onResize);
        },

        /**
         * Alpine calls destroy() when the component's root element is removed from
         * the DOM (e.g. a wire:navigate transition). Tear down every window/Echo/
         * BroadcastChannel/Livewire listener registered in boot() so they do not
         * leak across SPA navigations.
         */
        destroy() {
            this._destroyed = true;
            this._queuedPull = null;
            this._deferredPull = null;
            this.picker.seq++;

            clearTimeout(this._flushTimer);
            clearTimeout(this._serverTimer);
            clearTimeout(this._chargePopoverTimer);

            if (this._idleHandle !== null && 'cancelIdleCallback' in window) {
                cancelIdleCallback(this._idleHandle);
            }

            this._flushTimer = null;
            this._serverTimer = null;
            this._chargePopoverTimer = null;
            this._idleHandle = null;

            this.dragController?.destroy();
            this.dragController = null;

            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll, true);
                this._onScroll = null;
            }

            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
                this._onResize = null;
            }

            if (this._broadcast) {
                this._broadcast.close();
                this._broadcast = null;
            }

            if (this._echoChannel && typeof this._echoChannel.stopListening === 'function') {
                this._echoChannel.stopListening('.availability.changed');
                this._echoChannel = null;
            }

        },

        initBroadcast() {
            if (typeof BroadcastChannel === 'undefined') {
                return;
            }

            this._broadcast = new BroadcastChannel(`signals-quotation-editor-${this.quotationId}`);
            this._broadcast.onmessage = (event) => {
                if (event.data === 'invalidate') {
                    this.pullFromServer(true, { forceTree: true, reason: 'broadcast' });
                }
            };
        },

        initEcho() {
            if (!window.Echo || !this.echoChannel) {
                return;
            }

            this._echoChannel = window.Echo.private(this.echoChannel);
            this._echoChannel.listen('.availability.changed', () => {
                this.pullFromServer(true, { forceTree: true, reason: 'availability' });
            });
        },

        normalizeLifecycleDetail(event) {
            const raw = event?.detail ?? {};

            if (Array.isArray(raw) && raw.length === 1 && raw[0] && typeof raw[0] === 'object') {
                return raw[0];
            }

            return raw;
        },

        applyLifecycleFlags(detail) {
            if (detail.editable !== undefined) {
                this.editable = !!detail.editable;
            }

            if (detail.pricingFrozen !== undefined) {
                this.pricingFrozen = !!detail.pricingFrozen;
            }

            if (detail.priceLocked !== undefined) {
                this.priceLocked = !!detail.priceLocked;
            }

            if (detail.fieldsEditable !== undefined) {
                this.fieldsEditable = !!detail.fieldsEditable;
            } else {
                this.fieldsEditable = this.editable && !this.pricingFrozen;
            }

            if (detail.hasDealPrice !== undefined) {
                this.hasDealPrice = !!detail.hasDealPrice;

                if (detail.pricingFrozen === undefined && this.hasDealPrice) {
                    this.pricingFrozen = true;
                    this.fieldsEditable = false;
                }
            }

            if (detail.dealTotalRaw !== undefined) {
                this.dealPriceInput = detail.dealTotalRaw ?? '';
            }
        },

        notifyTabsInvalidate() {
            this._broadcast?.postMessage('invalidate');
        },

        replaceRows(nextRows) {
            const normalized = this.normalize(nextRows);

            if (this.rows.length === 0) {
                this.rows.push(...normalized);
            } else {
                this.rows.splice(0, this.rows.length, ...normalized);
            }
        },

        normalize(rows) {
            const clone = rows.map((r) => ({
                ...r,
                id: Number(r.id),
                depth: Number(r.depth),
                parent_group_id: r.parent_group_id != null ? Number(r.parent_group_id) : r.parent_group_id,
            }));

            for (const row of clone) {
                if (row.unit_price != null) {
                    row.unit_price_display = this.money(row.unit_price);
                }

                if (row.item_type !== 'group' && row.charge_total != null) {
                    row.charge_total_display = this.money(row.charge_total);
                }
            }

            return this.recomputeFlags(clone);
        },

        applyDefaultCollapse(rows) {
            for (let i = 0; i < rows.length; i++) {
                const r = rows[i];

                if (r.item_type === 'product') {
                    const next = rows[i + 1];
                    const hasAccessoryChild = !!(next && next.depth > r.depth && next.item_type === 'accessory');
                    r.is_collapsed = hasAccessoryChild;
                } else {
                    r.is_collapsed = false;
                }
            }

            return rows;
        },

        applyCollapsedSectionState(ids = []) {
            const collapsed = new Set(ids.map((id) => Number(id)));

            this.collapsedSectionIds = [...collapsed];

            for (const row of this.rows) {
                if (row.item_type === 'group') {
                    row.is_collapsed = collapsed.has(Number(row.id));
                }
            }
        },

        async syncCollapsedSectionState() {
            this.sectionStatePending = true;

            try {
                await this.$wire.setCollapsedSectionIds(this.collapsedSectionIds);
            } finally {
                this.sectionStatePending = false;
            }
        },

        recomputeFlags(rows) {
            for (let i = 0; i < rows.length; i++) {
                const next = rows[i + 1];
                rows[i].has_children = !!(next && next.depth > rows[i].depth);
            }

            return rows;
        },

        get visibleRows() {
            const out = [];
            let hideBelowDepth = Infinity;

            for (const r of this.rows) {
                if (r.depth > hideBelowDepth) {
                    continue;
                }

                hideBelowDepth = Infinity;
                out.push(r);
                if (r.is_collapsed) {
                    hideBelowDepth = r.depth;
                }
            }

            return out;
        },

        get hasSectionChildren() {
            return this.rows.some((row) => row.item_type === 'group' && row.has_children);
        },

        get grandTotal() {
            return this.rows
                .filter((r) => r.item_type !== 'group')
                .reduce((s, r) => s + (Number(r.charge_total) || 0), 0);
        },

        get displayGrandTotal() {
            return this.money(this.grandTotal);
        },

        get displayDealPriceSubline() {
            if (!this.hasDealPrice || this.serverDealTotalMinor == null) {
                return null;
            }

            return `Deal price applied — ${this.money(this.serverDealTotalMinor)}`;
        },

        applyServerTotals(payload = {}) {
            if (payload.charge_total != null) {
                this.serverChargeTotalMinor = Number(payload.charge_total) || 0;
            }

            if (payload.deal_total !== undefined) {
                this.serverDealTotalMinor = payload.deal_total != null ? Number(payload.deal_total) : null;
            }

            if (payload.has_deal_price !== undefined) {
                this.hasDealPrice = !!payload.has_deal_price;
            }
        },

        syncOptimisticTotalsFromRows() {
            const chargeTotalMinor = this.grandTotal;
            this.serverChargeTotalMinor = chargeTotalMinor;

            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('quotation-totals-updated', {
                    chargeTotalMinor,
                });
            }
        },

        async refreshBaseRevision() {
            try {
                this.baseRevision = normalizeRevision(await this.$wire.treeRevisionToken());
            } catch (e) {
                console.warn('line-items revision refresh failed', e);
            }
        },

        async syncTotalsFromServer() {
            try {
                const snap = await this.$wire.totalsSnapshot();

                if (snap) {
                    this.baseRevision = normalizeRevision(snap.revision) || this.baseRevision;
                    this.applyServerTotals(snap);
                    Livewire.dispatch('quotation-totals-updated', {
                        chargeTotalMinor: Number(snap.charge_total) || 0,
                    });
                }
            } catch (e) {
                console.warn('line-items totals sync failed', e);
            }
        },

        async recalculatePricing() {
            if (this.pricingRecalculating || this.mutationPending) {
                return;
            }

            this.pricingRecalculating = true;

            try {
                await this.$wire.recalculatePricing(false, false);
            } finally {
                this.pricingRecalculating = false;
            }
        },

        async onMutationDone(event) {
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));

            await this.pullFromServer(false, { mutation: true, forceTree: true });
            this.notifyTabsInvalidate();

            this.markRowsAddedSince(beforeIds);

            const detail = event?.detail ?? {};

            if (detail.modal) {
                this.$dispatch('close-modal', detail.modal);
            }
        },

        async onLifecycleChanged(event) {
            const detail = this.normalizeLifecycleDetail(event);

            this.applyLifecycleFlags(detail);

            this.applyServerTotals({
                charge_total: detail.chargeTotalMinor,
                deal_total: detail.dealTotalMinor,
                has_deal_price: detail.hasDealPrice,
            });

            await this.pullFromServer(false, {
                lifecycle: true,
                forceTree: true,
                reason: 'lifecycle',
            });
            this.notifyTabsInvalidate();

            // Pull does not re-read Alpine flags from the server; re-apply so lock/unlock is live.
            this.applyLifecycleFlags(detail);
        },

        _shouldDeferPull(opts = {}) {
            if (opts.force) {
                return false;
            }

            if (opts.afterFlush) {
                return hasStructuralPending(this.queue);
            }

            return this._flushing || hasStructuralPending(this.queue);
        },

        _queueDeferredPull(markConflicts, opts = {}) {
            this._deferredPull = this.mergePullRequest(this._deferredPull, markConflicts, opts);
        },

        async _drainDeferredPull() {
            if (!this._deferredPull || this._flushing || hasStructuralPending(this.queue)) {
                return;
            }

            const deferred = this._deferredPull;
            this._deferredPull = null;
            await this.pullFromServer(deferred.markConflicts, {
                ...deferred.opts,
                afterFlush: true,
            });
        },

        get openMenuRow() {
            return this.menuRow ?? this.resolveMenuRow();
        },

        resolveMenuRow() {
            if (this.openMenu == null) {
                return null;
            }

            return this.rows.find((row) => sameRowId(row.id, this.openMenu)) ?? null;
        },

        refreshMenuRow() {
            if (this.openMenu == null) {
                this.menuRow = null;

                return;
            }

            this.menuRow = this.resolveMenuRow();
        },

        get conflictCount() {
            return Object.keys(this.conflicts).length;
        },

        groupSubtotal(row) {
            const idx = this.findRowIndex(row.id);
            let sum = 0;

            for (let i = idx + 1; i < this.rows.length; i++) {
                if (this.rows[i].depth <= row.depth) {
                    break;
                }

                if (this.rows[i].item_type !== 'group') {
                    sum += Number(this.rows[i].charge_total) || 0;
                }
            }

            return sum;
        },

        groupSubtotalDisplay(row) {
            return this.money(this.groupSubtotal(row));
        },

        money(minor) {
            const amount = (Number(minor) / 100).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            return this.currencySymbol + amount;
        },

        recomputeChargeBreakdown(row) {
            if (! row || row.item_type === 'group') {
                if (row) {
                    row.charge_breakdown = null;
                }

                return;
            }

            const unitPriceMinor = Number(row.unit_price) || 0;
            const quantity = parseFloat(row.quantity) || 0;
            const days = Math.max(1, Number(row.days) || 0);
            const rentalMinor = Math.round(quantity * unitPriceMinor * days);
            const surchargeMinor = 0;

            row.charge_breakdown = {
                days_line: `Days: ${this.money(unitPriceMinor)} × ${days}`,
                rental_charge_display: this.money(rentalMinor),
                surcharge_display: this.money(surchargeMinor),
            };
        },

        formatMajor(amount) {
            return this.currencySymbol + (Number(amount) || 0).toFixed(2);
        },

        fmtQty(q) {
            return Number(q).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        },

        statusClass(label) {
            const map = {
                Available: 's-badge-green',
                Reserved: 's-badge-blue',
                Shortage: 's-badge-red',
            };

            return map[label] || 's-badge-zinc';
        },

        get syncLabel() {
            return { idle: 'Local', cached: 'Cached', syncing: 'Syncing…', synced: 'Synced' }[this.syncState];
        },

        async expandAll() {
            if (this.sectionStatePending) {
                return;
            }

            this.rows.forEach((r) => {
                r.is_collapsed = false;
            });
            this.collapsedSectionIds = [];
            await this.syncCollapsedSectionState();
        },

        async collapseAll() {
            if (this.sectionStatePending) {
                return;
            }

            this.collapsedSectionIds = this.rows
                .filter((r) => r.item_type === 'group' && r.has_children)
                .map((r) => Number(r.id));
            this.rows.forEach((r) => {
                if (r.has_children) {
                    r.is_collapsed = true;
                }
            });
            await this.syncCollapsedSectionState();
        },

        async toggleCollapse(id) {
            if (this.sectionStatePending) {
                return;
            }

            const row = this.findRow(id);

            if (!row) {
                return;
            }

            row.is_collapsed = !row.is_collapsed;
            const sectionId = Number(row.id);

            if (row.is_collapsed) {
                this.collapsedSectionIds = [...new Set([...this.collapsedSectionIds, sectionId])];
            } else {
                this.collapsedSectionIds = this.collapsedSectionIds.filter((collapsedId) => collapsedId !== sectionId);
            }

            await this.syncCollapsedSectionState();
        },

        groupMoveOptions(row) {
            if (row.item_type === 'group') {
                return [];
            }

            const opts = this.rows
                .filter((r) => r.item_type === 'group' && !sameRowId(r.id, row.parent_group_id))
                .map((r) => ({ id: Number(r.id), name: String(r.name ?? '') }));

            if (row.parent_group_id) {
                opts.unshift({ id: '', name: 'auto group' });
            }

            return opts;
        },

        hasManyGroups() {
            return this.rows.filter((row) => row.item_type === 'group').length > 3;
        },

        openGroupPanel(row) {
            if (!this.editable || !row || row.item_type === 'group') {
                return;
            }

            this.groupPanelRowId = row.id;
            this.groupPanelRowName = String(row.name || '');
            this.groupPanelOptions = this.groupMoveOptions(row);
            this.groupPanelOpen = true;
            this.closeRowMenu(true);
        },

        closeGroupPanel() {
            if (this.groupPanelPending) {
                return;
            }

            this.groupPanelOpen = false;
            this.groupPanelRowId = null;
            this.groupPanelRowName = '';
            this.groupPanelOptions = [];
        },

        async assignRowFromGroupPanel(groupId) {
            const row = this.rows.find((candidate) => sameRowId(candidate.id, this.groupPanelRowId));

            if (!row || this.groupPanelPending) {
                return;
            }

            this.groupPanelPending = true;

            try {
                await this.assignRowToGroup(row, groupId);
                this.groupPanelPending = false;
                this.closeGroupPanel();
            } finally {
                this.groupPanelPending = false;
            }
        },

        openEditLineModal(row) {
            this.$dispatch('open-modal', { id: 'edit-line', line: { ...row } });
        },

        async toggleOptionalRow(row) {
            await this.$wire.toggleOptional(row.id);
        },

        async mergeDupes(row) {
            await this.$wire.mergeDuplicates(row.id);
        },

        async assignRowToGroup(row, groupId) {
            await this.$wire.assignToGroup(row.id, groupId === '' ? null : Number(groupId));
        },

        openRowMenu(event, rowOrId) {
            event?.stopPropagation?.();

            const row = typeof rowOrId === 'object' && rowOrId !== null
                ? rowOrId
                : this.rows.find((candidate) => sameRowId(candidate.id, rowOrId));

            if (! row) {
                return;
            }

            if (sameRowId(this.openMenu, row.id)) {
                this.closeRowMenu(true);

                return;
            }

            this._menuOutsideSuppressedUntil = Date.now() + 300;
            this._menuAnchor = event.currentTarget;
            this.openMenu = row.id;
            this.menuRow = row;
            this.confirmDeleteId = null;

            this.$nextTick(() => {
                this.positionRowMenu();
                requestAnimationFrame(() => this.positionRowMenu());
            });
        },

        onRowMenuOutsideClick(event) {
            if (this._menuOutsideSuppressedUntil && Date.now() < this._menuOutsideSuppressedUntil) {
                return;
            }

            if (this._menuAnchor && (event.target === this._menuAnchor || this._menuAnchor.contains(event.target))) {
                return;
            }

            this.closeRowMenu(true);
        },

        closeRowMenu(force = false) {
            if (! force && this._menuOutsideSuppressedUntil && Date.now() < this._menuOutsideSuppressedUntil) {
                return;
            }

            this.openMenu = null;
            this.menuRow = null;
            this.confirmDeleteId = null;
            this._menuAnchor = null;
            this._menuOutsideSuppressedUntil = null;
        },

        async handleRemoveMenuClick() {
            const row = this.openMenuRow;

            if (! row) {
                return;
            }

            const id = row.id;

            if (! sameRowId(this.confirmDeleteId, id)) {
                this.confirmDeleteId = id;
                this._menuOutsideSuppressedUntil = Date.now() + 1500;

                return;
            }

            this.closeRowMenu(true);
            this.deleteNode(id);
        },

        positionRowMenu() {
            if (this.openMenu === null || ! this._menuAnchor) {
                return;
            }

            const rect = this._menuAnchor.getBoundingClientRect();

            this.menuPos = {
                top: rect.bottom + 4,
                right: window.innerWidth - rect.right,
            };
        },

        showChargePopover(event, row) {
            if (!row?.charge_breakdown) {
                return;
            }

            clearTimeout(this._chargePopoverTimer);

            const rect = event.currentTarget.getBoundingClientRect();
            const width = 220;

            this.chargePopover = {
                row,
                top: rect.bottom + 6,
                left: Math.max(8, rect.right - width),
            };
        },

        keepChargePopover() {
            clearTimeout(this._chargePopoverTimer);
        },

        hideChargePopoverSoon() {
            clearTimeout(this._chargePopoverTimer);
            this._chargePopoverTimer = setTimeout(() => {
                this.chargePopover = null;
            }, 120);
        },

        async applyDealPrice() {
            const val = this.dealPriceInput.trim();

            if (!val) {
                return;
            }

            await this.$wire.setDealPrice(val, this.dealPriceMode);
            this.hasDealPrice = true;
            this.pricingFrozen = this.dealPriceMode === 'flat_headline';
            this.fieldsEditable = this.dealPriceMode !== 'flat_headline' && this.editable && ! this.priceLocked;
            this.serverDealTotalMinor = Math.round(parseMoney(val) * 100);
        },

        async clearDealPrice() {
            await this.$wire.clearDealPrice();
            this.dealPriceInput = '';
            this.hasDealPrice = false;

            if (! this.priceLocked) {
                this.pricingFrozen = false;
                this.fieldsEditable = this.editable;
            }
        },

        async resetItemDiscount(itemId) {
            await this.$wire.resetItemDiscount(itemId);
            await this.syncTreeFromServer();
        },

        editChain: ['quantity', 'days', 'unit_price', 'discount_percent'],

        tabTarget(id, field, backwards) {
            const row = this.findRow(id);

            if (!row || row.item_type === 'group' || !this.fieldsEditable) {
                return null;
            }

            const chain = this.editChain;
            let i = chain.indexOf(field);

            if (i === -1) {
                return null;
            }

            i += backwards ? -1 : 1;

            if (i >= 0 && i < chain.length) {
                return { id, field: chain[i] };
            }

            if (!backwards && field === 'discount_percent') {
                const visibleIds = new Set(this.visibleRows.map((r) => r.id));
                const idx = this.findRowIndex(id);

                for (let j = idx + 1; j < this.rows.length; j++) {
                    const next = this.rows[j];

                    if (next.item_type !== 'group' && visibleIds.has(next.id)) {
                        return { id: next.id, field: 'quantity' };
                    }
                }
            }

            return null;
        },

        resolveEditHost(tr, field) {
            if (!tr) {
                return null;
            }

            if (field === 'name') {
                return tr.querySelector('.lf-name:not(.lf-product-link)') || tr.querySelector('.lf-name');
            }

            return tr.querySelector(`.lf-cell[data-field="${field}"]`);
        },

        tryFocusField(id, field) {
            const tr = this.$refs.tbody?.querySelector(`tr[data-id="${id}"]`);

            if (!tr) {
                return false;
            }

            const host = this.resolveEditHost(tr, field);

            if (!host) {
                return false;
            }

            this.beginEdit(id, field, { currentTarget: host, target: host });

            return true;
        },

        async focusFieldWhenReady(id, field, attempt = 0) {
            if (this.tryFocusField(id, field)) {
                return true;
            }

            if (attempt >= 15) {
                return false;
            }

            await new Promise((resolve) => requestAnimationFrame(resolve));

            return this.focusFieldWhenReady(id, field, attempt + 1);
        },

        focusField(id, field) {
            this.focusFieldWhenReady(id, field);
        },

        openLockPriceModal() {
            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('open-quotation-lock-price-modal');
            }
        },

        toastError(message) {
            if (!message) {
                return;
            }

            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('toast', { type: 'error', message });
            }
        },

        flushErrorMessage(error) {
            const payload = error?.response?.data ?? error?.body ?? error ?? {};
            const errors = payload?.errors ?? {};
            const firstFieldError = Object.values(errors).flat()?.[0];

            return String(
                firstFieldError
                ?? payload?.message
                ?? error?.message
                ?? 'Could not save line-item changes.',
            );
        },

        isPricingGuardError(error) {
            const message = this.flushErrorMessage(error).toLowerCase();

            return message.includes('pricing is frozen')
                || message.includes('cannot be removed')
                || message.includes('cannot be edited while pricing is frozen');
        },

        beginEdit(id, field, ev) {
            if (!this.editable || !this.fieldsEditable) {
                return;
            }

            const row = this.findRow(id);

            if (!row) {
                return;
            }

            if (row.item_type === 'group' && field !== 'name') {
                return;
            }

            const cell = ev ? ev.currentTarget : null;
            const isText = field === 'name';
            let current;

            if (field === 'unit_price') {
                current = row.unit_price_raw ?? (Number(row.unit_price || 0) / 100).toFixed(2);
            } else if (field === 'discount_percent') {
                current = row.discount_percent || '';
            } else {
                current = row[field];
            }

            const input = document.createElement('input');
            const widthClass = isText
                ? ' lf-edit-text'
                : field === 'unit_price'
                    ? ' lf-edit-price'
                    : field === 'quantity'
                        ? ' lf-edit-qty'
                        : field === 'days'
                            ? ' lf-edit-days'
                            : field === 'discount_percent'
                                ? ' lf-edit-disc'
                                : '';
            input.className = 'lf-edit-input' + widthClass;
            input.type = 'text';
            input.value = current ?? '';

            const host = cell || (ev ? ev.target : null) || this.$refs.tbody?.querySelector(`tr[data-id="${id}"] .lf-name`);

            if (!host) {
                return;
            }

            const prevHTML = host.style.display;
            host.style.display = 'none';
            host.parentNode.insertBefore(input, host.nextSibling);
            input.focus();
            input.select();

            const commit = (save) => {
                if (input._done) {
                    return;
                }

                input._done = true;
                const val = input.value.trim();
                input.remove();
                host.style.display = prevHTML;

                if (save) {
                    if (field === 'name' && (row.item_type === 'text' || row.item_type === 'group') && val === '') {
                        this.discardBlankInlineRow(id);

                        return;
                    }

                    this.applyField(id, field, field === 'name' ? val : input.value.trim());
                }
            };

            input.addEventListener('blur', () => commit(true));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    commit(true);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    commit(false);
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    commit(true);
                    const target = this.tabTarget(id, field, e.shiftKey);

                    if (target) {
                        this.focusField(target.id, target.field);
                    }
                }
            });
        },

        discardBlankInlineRow(id) {
            const row = this.findRow(id);

            if (!row) {
                return;
            }

            if (Number(row.id) > 0) {
                void this.deleteNode(id);

                return;
            }

            const idx = this.findRowIndex(id);

            if (idx !== -1) {
                this.rows.splice(idx, 1);
            }

            this.queue = this.queue.filter((m) => m.tmpId !== id && m.id !== id);
            this.afterLocalMutation();
        },

        applyField(id, field, value) {
            const row = this.findRow(id);

            if (! row) {
                return;
            }

            if (field === 'name' && (row.item_type === 'text' || row.item_type === 'group') && String(value).trim() === '') {
                this.discardBlankInlineRow(id);

                return;
            }

            if (field === 'unit_price') {
                const major = parseMoney(value);
                row.unit_price = Math.round(major * 100);
                row.unit_price_raw = major.toFixed(2);
                row.unit_price_display = this.money(row.unit_price);
            } else if (field === 'quantity') {
                row.quantity = String(Math.round((parseFloat(value) || 0) * 100) / 100);
            } else if (field === 'days') {
                row.days = Math.max(0, parseInt(value || '0', 10));
            } else if (field === 'discount_percent') {
                row.discount_percent = value === '' ? null : String(Math.round((parseFloat(value) || 0) * 100) / 100);
            } else {
                row[field] = value;
            }

            if (['unit_price', 'quantity', 'days', 'discount_percent'].includes(field)) {
                const disc = row.discount_percent ? parseFloat(row.discount_percent) : 0;
                const gross = (parseFloat(row.quantity) || 0) * (row.days || 0) * (row.unit_price || 0);
                row.charge_total = Math.round(gross * (1 - disc / 100));
                row.charge_total_display = this.money(row.charge_total);
                this.recomputeChargeBreakdown(row);
            }

            this.afterLocalMutation();
            this.enqueue({ kind: 'field', id, field, value });
        },

        addGroup(parentGroupId = null, name = '') {
            const tmpId = this.tempId();
            let depth = 1;
            let insertAt = this.rows.length;

            if (parentGroupId != null) {
                const pIdx = this.rows.findIndex((r) => r.id === parentGroupId);

                if (pIdx !== -1) {
                    depth = this.rows[pIdx].depth + 1;
                    insertAt = pIdx + 1;

                    while (insertAt < this.rows.length && this.rows[insertAt].depth > this.rows[pIdx].depth) {
                        insertAt++;
                    }
                }
            }

            this.rows.splice(insertAt, 0, this.blankRow(tmpId, 'group', depth, name));
            this.markRowJustAdded(tmpId);
            this.afterLocalMutation();
            this.enqueue({ kind: 'addGroup', tmpId, parentGroupId, name });
        },

        findDeleteBlock(id) {
            const idx = this.findRowIndex(id);

            if (idx === -1) {
                return null;
            }

            const target = this.rows[idx];
            let end = idx + 1;

            while (end < this.rows.length && this.rows[end].depth > target.depth) {
                end++;
            }

            return {
                idx,
                end,
                target,
            };
        },

        spliceLocalDeleteBlock(block) {
            const { idx, end } = block;
            this.rows.splice(idx, end - idx);
            this.afterLocalMutation();
            this.syncOptimisticTotalsFromRows();
        },

        deleteItemUrl(serverId) {
            // Group/section deletes always cascade their subtree server-side via
            // RemoveQuotationItem cascades the subtree server-side, so there is no scope parameter —
            // the controller does not read one.
            return `/quotations/${this.quotationId}/items/${serverId}`;
        },

        sendKeepaliveDelete(serverId) {
            const token = this.csrfToken
                || document.querySelector('meta[name="csrf-token"]')?.content
                || '';

            fetch(this.deleteItemUrl(serverId), {
                method: 'DELETE',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
            }).then(async (res) => {
                if (! res.ok) {
                    const body = await res.text();
                    throw new Error(body || `HTTP ${res.status}`);
                }

                void this.syncTotalsFromServer();
            }).catch((e) => {
                console.error('keepalive delete failed', e);
                this.toastError(this.flushErrorMessage(e));
                void this.pullFromServer(false, { mutation: true });
            });
        },

        deleteNode(id) {
            const row = this.rows.find((candidate) => sameRowId(candidate.id, id));
            const canRemoveWhilePricingFrozen = row
                && ((row.item_type === 'group' && ! row.has_children)
                    || (row.item_type === 'text' && ! String(row.name || '').trim()));

            if (this.pricingFrozen && ! canRemoveWhilePricingFrozen) {
                this.toastError('Line items cannot be removed while pricing is frozen.');

                return;
            }

            const block = this.findDeleteBlock(id);

            if (! block) {
                return;
            }

            const serverId = this.resolveServerItemId(id);

            for (let i = block.idx; i < block.end; i++) {
                this.rows[i]._removing = true;
            }

            if (serverId !== null) {
                this.sendKeepaliveDelete(serverId);
            } else {
                // A not-yet-persisted (temp-ID) row: purge any queued add mutation
                // for it so the next flush does not re-create the row we just
                // deleted (a ghost row). Mirrors discardBlankInlineRow's cleanup.
                this.queue = this.queue.filter((m) => ! sameRowId(m.tmpId, id) && ! sameRowId(m.id, id));
            }

            this.notifyTabsInvalidate();

            setTimeout(() => {
                const current = this.findDeleteBlock(id);

                if (! current) {
                    return;
                }

                this.spliceLocalDeleteBlock(current);
            }, 350);
        },

        tempId() {
            return -1 * (Date.now() * 1000 + (++this._tempSeq));
        },

        blankRow(id, type, depth, name) {
            const row = {
                id,
                item_type: type,
                depth,
                name,
                quantity: '1',
                days: 1,
                unit_price: 0,
                unit_price_display: this.money(0),
                unit_price_raw: '0.00',
                discount_percent: null,
                charge_total: 0,
                charge_total_display: this.money(0),
                type_label: type === 'group' ? null : 'Rental',
                status_label: type === 'group' ? null : 'Reserved',
                is_collapsed: false,
                has_children: false,
                is_optional: false,
                has_duplicates: false,
            };

            this.recomputeChargeBreakdown(row);

            return row;
        },

        afterLocalMutation() {
            this.recomputeFlags(this.rows);
        },

        onHandleDown(event, id) {
            this.dragController?.onHandleDown(event, id);
        },

        enqueue(mutation) {
            if (!this.editable) {
                return;
            }

            this.queue.push(mutation);
            this.scheduleFlush();
        },

        scheduleFlush() {
            this.setSync('syncing');
            clearTimeout(this._flushTimer);
            this._flushTimer = setTimeout(() => this.idleFlush(), 250);
        },

        idleFlush() {
            if ('requestIdleCallback' in window) {
                this._idleHandle = requestIdleCallback(() => this.flush(), { timeout: 1500 });
            } else {
                this.flush();
            }
        },

        async flush() {
            if (this._flushing) {
                this._pendingFlush = true;

                return;
            }

            if (! this.queue.length) {
                this.setSync('synced');

                return;
            }

            this._flushing = true;
            this.setSync('syncing');

            const batch = orderFlushBatch(this.queue.splice(0, this.queue.length));
            const requeue = [];
            let caught = false;

            try {
                const serverStateWasSynced = await this.applyBatchToServer(batch, requeue);
                this.setSync(this.queue.length || requeue.length ? 'syncing' : 'synced');

                if (!serverStateWasSynced) {
                    await this.syncTotalsFromServer();
                }

                this.notifyTabsInvalidate();
            } catch (e) {
                caught = true;
                const message = this.flushErrorMessage(e);
                console.error('line-items flush failed, re-queueing', message, e);
                this.toastError(message);
                this.setSync('syncing');
                setTimeout(() => this.scheduleFlush(), 1500);
            } finally {
                // Single source of truth for what gets re-queued: on error the full
                // batch (once), on success the partial requeue (once). Never both.
                const toRequeue = mutationsToRequeue({ caught, batch, requeue });

                if (toRequeue.length) {
                    this.queue.unshift(...toRequeue);
                }

                this._flushing = false;

                const needsRetry = shouldScheduleFlushRetry({
                    wasBlocked: false,
                    queueLength: this.queue.length,
                    pendingFlushFlag: this._pendingFlush,
                });

                this._pendingFlush = false;

                if (needsRetry) {
                    this.scheduleFlush();
                } else {
                    await this._drainDeferredPull();
                }
            }
        },

        resolveServerItemId(id) {
            return resolveServerItemId(id, this.rows);
        },

        requeueMutation(mutation, reason, requeue = null) {
            this.toastError(reason);

            if (Array.isArray(requeue)) {
                requeue.push(mutation);

                return;
            }

            this.queue.unshift(mutation);
        },

        async applyBatchToServer(batch, requeue = []) {
            let structural = false;
            const fieldUpdates = new Map();

            for (const m of batch) {
                try {
                    if (m.kind === 'addGroup') {
                        const realId = await this.$wire.addGroup(m.parentGroupId ?? null, m.name ?? '');
                        this.remapTempId(m.tmpId, realId);
                        structural = true;
                    } else if (m.kind === 'addProduct') {
                        await this.$wire.addProduct(m.productId, m.quantity, m.destination);
                        structural = true;
                    } else if (m.kind === 'field') {
                        const serverId = this.resolveServerItemId(m.id);

                        if (serverId === null) {
                            this.requeueMutation(
                                m,
                                'Still saving that line — your edit will retry automatically.',
                                requeue,
                            );

                            continue;
                        }

                        const row = this.rows.find((r) => Number(r.id) === serverId);
                        let value = m.value;

                        if (row) {
                            if (m.field === 'unit_price') {
                                value = row.unit_price_raw ?? (Number(row.unit_price || 0) / 100).toFixed(2);
                            } else if (m.field === 'discount_percent') {
                                value = row.discount_percent ?? '';
                            } else {
                                value = row[m.field];
                            }
                        }

                        fieldUpdates.set(`${serverId}:${m.field}`, {
                            id: serverId,
                            field: m.field,
                            value,
                        });
                    } else if (m.kind === 'persistTree') {
                        structural = true;
                    }
                } catch (e) {
                    throw e;
                }
            }

            let fieldStateWasSynced = false;

            if (fieldUpdates.size) {
                const snapshot = await this.$wire.applyFieldMutations([...fieldUpdates.values()]);
                this.baseRevision = normalizeRevision(snapshot?.revision) || this.baseRevision;
                this.applyServerTotals(snapshot);
                fieldStateWasSynced = true;
            }

            if (structural) {
                const realRows = rowsEligibleForPersistTree(this.rows);
                let structuralPersistSucceeded = false;

                if (realRows.length) {
                    await this.refreshBaseRevision();

                    const nodes = realRows.map((r) => ({ id: r.id, depth: r.depth }));
                    const baseRevisionBeforePersist = this.baseRevision;

                    console.warn('line-items persistTree call', {
                        baseRevision: baseRevisionBeforePersist,
                        nodeCount: nodes.length,
                        order: nodes.map((n) => n.id).join(','),
                        pruneOrphans: true,
                    });

                    const result = await this.$wire.persistTree(nodes, baseRevisionBeforePersist);

                    console.warn('line-items persistTree result', {
                        stale: !!result?.stale,
                        revision: result?.revision ?? null,
                        revisionDrift: !!result?.revision_drift,
                        baseRevision: result?.base_revision ?? baseRevisionBeforePersist,
                        serverRevisionBefore: result?.server_revision_before ?? null,
                    });

                    if (result?.revision_drift) {
                        console.warn('line-items persistTree applied despite revision drift', {
                            baseRevision: result?.base_revision ?? baseRevisionBeforePersist,
                            serverRevisionBefore: result?.server_revision_before ?? null,
                            newRevision: result?.revision ?? null,
                        });
                    }

                    if (result?.stale) {
                        console.error('line-items persistTree returned stale unexpectedly', {
                            baseRevision: baseRevisionBeforePersist,
                            serverRevision: result?.revision ?? null,
                            nodeCount: nodes.length,
                        });

                        if (! requeue.some((mutation) => mutation.kind === 'persistTree')) {
                            requeue.push({ kind: 'persistTree' });
                        }

                        await this.handleStalePull('persistTree-unexpected-stale');

                        return true;
                    }

                    structuralPersistSucceeded = true;

                    if (result?.revision != null) {
                        this.baseRevision = normalizeRevision(result.revision);
                    }
                }

                await this.pullFromServer(false, {
                    afterFlush: true,
                    afterStructuralPersist: structuralPersistSucceeded,
                    forceTree: true,
                    reason: structuralPersistSucceeded ? 'post-structural-persist-sync' : 'post-structural-flush',
                });
            }

            return structural || fieldStateWasSynced;
        },

        remapTempId(tmpId, realId) {
            const tmp = Number(tmpId);
            const real = Number(realId);

            const row = this.rows.find((r) => Number(r.id) === tmp);

            if (row) {
                row.id = real;
            }

            for (const r of this.rows) {
                if (Number(r.parent_group_id) === tmp) {
                    r.parent_group_id = real;
                }
            }

            for (const m of this.queue) {
                if (Number(m.id) === tmp) {
                    m.id = real;
                }

                if (Number(m.tmpId) === tmp) {
                    m.tmpId = real;
                }

                if (Number(m.parentGroupId) === tmp) {
                    m.parentGroupId = real;
                }
            }

            if (sameRowId(this.openMenu, tmp)) {
                this.openMenu = real;
            }

            if (sameRowId(this.confirmDeleteId, tmp)) {
                this.confirmDeleteId = real;
            }

            this.refreshMenuRow();
        },

        _pullReason(opts = {}) {
            if (opts.reason) {
                return opts.reason;
            }

            if (opts.afterStructuralPersist) {
                return 'after-structural-persist';
            }

            if (opts.afterFlush) {
                return 'after-flush';
            }

            if (opts.mutation) {
                return 'mutation';
            }

            if (opts.lifecycle) {
                return 'lifecycle';
            }

            return 'manual';
        },

        async handleStalePull(reason = 'unknown') {
            console.error('line-items handleStalePull — server tree will replace local rows', {
                reason,
                baseRevision: this.baseRevision,
                localOrder: this.rows.map((r) => r.id).join(','),
            });
            await this.pullFromServer(true, { afterFlush: true, reason: `handleStalePull:${reason}` });
        },

        mergePullRequest(current, markConflicts, opts) {
            if (!current) {
                return { markConflicts, opts: { ...opts } };
            }

            return {
                markConflicts: current.markConflicts || markConflicts,
                opts: {
                    ...current.opts,
                    ...opts,
                    force: !!(current.opts.force || opts.force),
                    forceTree: !!(current.opts.forceTree || opts.forceTree),
                    afterFlush: !!(current.opts.afterFlush || opts.afterFlush),
                    afterStructuralPersist: !!(
                        current.opts.afterStructuralPersist || opts.afterStructuralPersist
                    ),
                },
            };
        },

        async pullFromServer(markConflicts = true, opts = {}) {
            if (this._destroyed) {
                return;
            }

            if (this._shouldDeferPull(opts)) {
                this._queueDeferredPull(markConflicts, opts);

                return;
            }

            this._queuedPull = this.mergePullRequest(this._queuedPull, markConflicts, opts);

            if (this._pullPromise) {
                return this._pullPromise;
            }

            this._pullPromise = (async () => {
                while (this._queuedPull && !this._destroyed) {
                    const request = this._queuedPull;
                    this._queuedPull = null;

                    if (this._shouldDeferPull(request.opts)) {
                        this._queueDeferredPull(request.markConflicts, request.opts);

                        continue;
                    }

                    await this.performPullFromServer(request.markConflicts, request.opts);
                }
            })().finally(() => {
                this._pullPromise = null;
            });

            return this._pullPromise;
        },

        async performPullFromServer(markConflicts, opts) {
            const reason = this._pullReason(opts);
            const trackOrder = !!(opts.afterFlush || opts.afterStructuralPersist);
            const orderBefore = trackOrder ? this.rows.map((r) => r.id).join(',') : null;
            const pending = pendingLocalIdsFromQueue(this.queue);
            const pendingIds = new Set(pending.map((id) => Number(id)));
            const localRowsForPull = opts.afterStructuralPersist
                ? []
                : this.rows.filter((row) => Number(row.id) < 0 || pendingIds.has(Number(row.id)));

            const payload = await this.$wire.pullTree(
                this.baseRevision,
                localRowsForPull,
                pending,
                !!opts.forceTree,
            );

            if (!payload || this._destroyed) {
                return;
            }

            this.applyServerTotals(payload);
            this.baseRevision = normalizeRevision(payload.revision) || this.baseRevision;

            if (payload.changed === false || !Object.hasOwn(payload, 'tree')) {
                return;
            }

            const serverTree = payload.tree || [];

            if (payload.stale && markConflicts) {
                const reconciled = reconcileLocalTree(this.rows, serverTree, pending);
                this.replaceRows(this.enrichRowsWithServerMetadata(reconciled.rows, serverTree));
                this.conflicts = reconciled.conflicts;
            } else {
                this.replaceRows(this.enrichRowsWithServerMetadata(serverTree, serverTree));
                this.conflicts = payload.conflicts || {};
            }

            if (orderBefore !== null) {
                const orderAfter = this.rows.map((r) => r.id).join(',');

                if (orderBefore !== orderAfter) {
                    console.warn('line-items pull reverted or replaced local tree order', {
                        reason,
                        before: orderBefore,
                        after: orderAfter,
                        stale: !!payload.stale,
                        markConflicts,
                        afterStructuralPersist: !!opts.afterStructuralPersist,
                        serverRevision: payload.revision ?? null,
                    });
                }
            }

            this.applyDefaultCollapse(this.rows);
            this.applyCollapsedSectionState(payload.collapsed_section_ids ?? this.collapsedSectionIds);
            this.refreshMenuRow();
        },

        onPickerInput(target, raw, isQuickAdd, opts = {}) {
            if (!this._searchController) {
                return;
            }

            const parsed = this._searchController.parseQuickAdd(raw);
            this.picker.target = target;
            this.picker.isQuickAdd = isQuickAdd;
            this.picker.quantity = parsed.quantity;
            this.picker.query = parsed.term;
            this.picker.mode = opts.mode || 'add';
            this.picker.substituteItemId = opts.itemId ?? null;

            if (isQuickAdd) {
                if (parsed.quantity > 1) {
                    this.quickAddQty = parsed.quantity;
                }

                this.quickAddQtyHint = parsed.quantity > 1 ? parsed.quantity + '×' : '';
            }

            if (parsed.term.trim().length < 2) {
                this.picker.results = [];
                this.picker.open = false;

                return;
            }

            const local = this._searchController.searchLocal(parsed.term);
            this.applyPickerResults(local);
            this.picker.loading = true;
            this.picker.open = true;
            this.positionPicker();

            const mySeq = ++this.picker.seq;
            clearTimeout(this._serverTimer);
            this._serverTimer = setTimeout(async () => {
                let server = [];

                try {
                    server = await this._searchController.searchServer(parsed.term);
                } catch {
                    server = [];
                }

                if (mySeq !== this.picker.seq) {
                    return;
                }

                this.applyPickerResults(this._searchController.merge(local, server));
                this.picker.loading = false;
            }, 250);
        },

        applyPickerResults(results) {
            this.picker.results = results;
            this.picker.highlight = 0;
            this.picker.localCount = results.filter((r) => r.source !== 'server').length;
            this.picker.serverCount = results.filter((r) => r.source === 'server').length;
        },

        resetQuickAddInput() {
            this.quickAddQuery = '';
            this.quickAddSelection = null;
            this.quickAddQtyHint = '';

            if (this.picker.target) {
                this.picker.target.value = '';
            }

            this.refocusQuickAddInput();
        },

        refocusQuickAddInput() {
            this.$refs.quickAddQty?.blur();

            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    this.$refs.quickAddInput?.focus({ preventScroll: true });
                });
            });
        },

        clearQuickAddAfterCommit() {
            this.picker.open = false;
            this.picker.results = [];
            this.quickAddQty = '';
            this.resetQuickAddInput();
        },

        onQuickAddInputKeydown(event, target) {
            if (this.picker.open) {
                this.onPickerKeydown(event, target, true);

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                this.picker.open = false;
                this.$refs.quickAddQty?.focus();
            }
        },

        onQuickAddQtyKeydown(event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const committed = this.commitQuickAdd();

            if (committed) {
                return;
            }

            this.refocusQuickAddInput();
        },

        commitQuickAdd() {
            if (this.mutationPending) {
                return false;
            }

            const hit = this.quickAddSelection
                ?? this.picker.results[this.picker.highlight]
                ?? this.picker.results[0];

            if (!hit) {
                return false;
            }

            const qty = Number(this.quickAddQty) > 0 ? Number(this.quickAddQty) : 1;
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));

            this.clearQuickAddAfterCommit();

            this.mutationPending = true;
            const addAction = hit.is_combination ? 'addCombination' : 'quickAdd';

            this.$wire[addAction](hit.id, qty).then(async () => {
                await this.pullFromServer(false, {
                    mutation: true,
                    force: true,
                    forceTree: true,
                });
                this.markRowsAddedSince(beforeIds);
                this.refocusQuickAddInput();
            }).finally(() => {
                this.mutationPending = false;
            });

            return true;
        },

        async createInlineTextLine() {
            if (!this.editable || this.structuralMutationPending) {
                return;
            }

            this.structuralMutationPending = true;
            let itemId = 0;
            const startedAt = performance.now();

            try {
                const destination = this.$root.querySelector('#quick-add-destination')?.value || '';
                const snapshot = await this.$wire.addInlineTextLineSnapshot(destination);
                itemId = Number(snapshot?.item_id) || 0;
                this.baseRevision = normalizeRevision(snapshot?.revision) || this.baseRevision;

                if (itemId > 0) {
                    this.rows.push(this.blankRow(itemId, 'text', 1, ''));
                    this.afterLocalMutation();
                    logStructuralAdd('text item created via lightweight path', {
                        itemId,
                        durationMs: Math.round(performance.now() - startedAt),
                    });
                }
            } catch (error) {
                if (isDevelopmentBuild) {
                    console.warn('[quotation-editor] text item creation fallback -> pullTree', error);
                }
                await this.pullFromServer(false, { mutation: true, force: true });
                throw error;
            } finally {
                this.structuralMutationPending = false;
            }

            if (itemId > 0) {
                await this.$nextTick();
                await this.focusFieldWhenReady(itemId, 'name');
            }
        },

        async createInlineSection() {
            if (!this.editable || this.structuralMutationPending) {
                return;
            }

            this.structuralMutationPending = true;
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));
            let groupId = 0;
            const startedAt = performance.now();

            try {
                const snapshot = await this.$wire.addInlineSectionSnapshot();
                groupId = Number(snapshot?.item_id) || 0;
                this.baseRevision = normalizeRevision(snapshot?.revision) || this.baseRevision;

                if (groupId > 0) {
                    this.rows.push(this.blankRow(groupId, 'group', 1, ''));
                    this.afterLocalMutation();
                    this.markRowsAddedSince(beforeIds);

                    logStructuralAdd('section created via lightweight path', {
                        groupId,
                        optionsRefreshed: false,
                        durationMs: Math.round(performance.now() - startedAt),
                    });
                }
            } catch (error) {
                if (isDevelopmentBuild) {
                    console.warn('[quotation-editor] section creation fallback -> pullTree', error);
                }
                await this.pullFromServer(false, { mutation: true, force: true });
                throw error;
            } finally {
                this.structuralMutationPending = false;
            }

            if (groupId > 0) {
                await this.$nextTick();
                await this.focusFieldWhenReady(groupId, 'name');
            }
        },

        stageQuickAddSelection(hit) {
            if (!hit) {
                return;
            }

            this.quickAddSelection = hit;
            this.quickAddQuery = hit.name ?? '';
            this.picker.open = false;

            if (this.picker.target) {
                this.picker.target.value = hit.name ?? '';
                this.picker.target.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this.$refs.quickAddQty?.focus();
        },

        onPickerKeydown(event, target, isQuickAdd) {
            if (!this.picker.open) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight + 1) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight - 1 + this.picker.results.length) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'Enter') {
                event.preventDefault();

                if (isQuickAdd) {
                    this.stageQuickAddSelection(this.picker.results[this.picker.highlight]);

                    return;
                }

                const hit = this.picker.results[this.picker.highlight];

                if (hit) {
                    this.choosePickerHit(hit);
                }
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                this.picker.open = false;
            }
        },

        choosePickerHit(hit) {
            if (this.picker.mode === 'substitute' && this.picker.substituteItemId) {
                this.$wire.substituteItem(this.picker.substituteItemId, hit.id);
                this.picker.open = false;

                return;
            }

            if (this.picker.isQuickAdd) {
                this.stageQuickAddSelection(hit);

                return;
            }

            const qty = this.picker.quantity || 1;
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));

            if (this.mutationPending) {
                return;
            }

            this.mutationPending = true;
            const addAction = hit.is_combination ? 'addCombination' : 'quickAdd';

            this.$wire[addAction](hit.id, qty).then(async () => {
                await this.pullFromServer(false, {
                    mutation: true,
                    force: true,
                    forceTree: true,
                });
                this.markRowsAddedSince(beforeIds);
            }).finally(() => {
                this.mutationPending = false;
            });

            this.picker.open = false;
            this.picker.results = [];
        },

        positionPicker() {
            if (!this.picker.open || !this.picker.target) {
                return;
            }

            const rect = this.picker.target.getBoundingClientRect();
            const dd = this.$refs.pickerDropdown;

            if (!dd) {
                return;
            }

            dd.style.left = (rect.left + window.scrollX) + 'px';
            dd.style.top = (rect.bottom + window.scrollY + 3) + 'px';
            dd.style.minWidth = Math.max(rect.width, 300) + 'px';
        },

        closePickerSoon() {
            setTimeout(() => {
                this.picker.open = false;
            }, 150);
        },

        setSync(state) {
            this.syncState = state;
        },

    };
}

function parseMoney(str) {
    const cleaned = String(str ?? '').replace(/[^0-9.\-]/g, '');

    return parseFloat(cleaned) || 0;
}
