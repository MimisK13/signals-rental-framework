import {
    INDENT_PX,
    canPlace,
    parentAt,
    resolveDropTarget,
} from '../../line-item-drop-target.js';

/**
 * Pointer-based tree dragging for the flat quotation table.
 *
 * The controller owns only the gesture and drop-target calculation. The editor
 * remains responsible for applying the optimistic rows and persisting them.
 */
export function createTreeDragController(options = {}) {
    const {
        getRows,
        getEditable,
        getTbody,
        getGhost,
        setDragState,
        onDrop,
        onInvalidDrop,
    } = options;

    let drag = null;
    let moveListener = null;
    let upListener = null;
    let cancelListener = null;
    let scrollListener = null;
    let resizeListener = null;
    let placeholder = null;
    let placeholderContent = null;
    let placeholderLabel = null;
    let placeholderState = null;
    let pointerFrame = null;
    let latestPointer = null;
    let geometryDirty = false;

    const updateState = (state) => {
        setDragState?.(state);
    };

    const removePlaceholder = () => {
        placeholder?.remove();
        placeholder = null;
        placeholderContent = null;
        placeholderLabel = null;
        placeholderState = null;
    };

    const clearPointerFrame = () => {
        if (pointerFrame !== null) {
            window.cancelAnimationFrame(pointerFrame);
            pointerFrame = null;
        }

        latestPointer = null;
    };

    const clearListeners = () => {
        if (moveListener) {
            window.removeEventListener('pointermove', moveListener);
            moveListener = null;
        }

        if (upListener) {
            window.removeEventListener('pointerup', upListener);
            upListener = null;
        }

        if (cancelListener) {
            window.removeEventListener('pointercancel', cancelListener);
            cancelListener = null;
        }

        if (scrollListener) {
            window.removeEventListener('scroll', scrollListener, true);
            scrollListener = null;
        }

        if (resizeListener) {
            window.removeEventListener('resize', resizeListener);
            resizeListener = null;
        }
    };

    const clearGhost = () => {
        const ghost = getGhost?.();

        if (!ghost) {
            return;
        }

        ghost.textContent = '';
        ghost.style.transform = '';
    };

    const cleanup = () => {
        clearPointerFrame();
        clearListeners();
        removePlaceholder();
        clearGhost();
        drag = null;
        geometryDirty = false;
        updateState({ dragId: null, nestDropGroupId: null });
    };

    const moveGhost = (x, y) => {
        const ghost = getGhost?.();

        if (ghost) {
            ghost.style.transform = `translate(${x + 14}px, ${y + 8}px)`;
        }
    };

    const ensurePlaceholder = () => {
        if (placeholder) {
            return;
        }

        placeholder = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 11;

        placeholderContent = document.createElement('div');
        placeholderContent.className = 'flex items-center gap-2 rounded border border-dashed border-[var(--brand-primary)] bg-[var(--surface-muted)] px-3 py-1.5 text-xs font-medium text-[var(--brand-primary)]';

        const bar = document.createElement('span');
        bar.className = 'lf-placeholder-bar w-1.5 shrink-0';
        placeholderContent.appendChild(bar);

        placeholderLabel = document.createElement('span');
        placeholderContent.appendChild(placeholderLabel);

        cell.appendChild(placeholderContent);
        placeholder.appendChild(cell);
    };

    const renderPlaceholder = (beforeId, depth, valid, message) => {
        const tbody = getTbody?.();

        if (!tbody) {
            return;
        }

        const normalizedBeforeId = beforeId == null ? null : Number(beforeId);
        const nextState = {
            beforeId: normalizedBeforeId,
            depth,
            valid,
            message,
        };

        if (placeholder?.parentNode === tbody
            && placeholderState
            && placeholderState.beforeId === nextState.beforeId
            && placeholderState.depth === nextState.depth
            && placeholderState.valid === nextState.valid
            && placeholderState.message === nextState.message) {
            return;
        }

        ensurePlaceholder();
        placeholder.className = `lf-placeholder${valid ? '' : ' lf-placeholder-invalid'}`;
        placeholderContent.style.marginLeft = `${(Math.max(1, depth) - 1) * INDENT_PX + 24}px`;
        placeholderLabel.textContent = message;

        if (normalizedBeforeId === null) {
            tbody.appendChild(placeholder);
        } else {
            const cachedReference = drag?.rowElements?.get(normalizedBeforeId);
            const reference = cachedReference?.parentNode === tbody
                ? cachedReference
                : tbody.querySelector(`tr[data-id="${normalizedBeforeId}"]`);

            if (reference) {
                tbody.insertBefore(placeholder, reference);
            } else {
                tbody.appendChild(placeholder);
            }
        }

        placeholderState = nextState;
    };

    const refreshGeometry = () => {
        if (!drag) {
            return false;
        }

        const tbody = getTbody?.();

        if (!tbody) {
            return false;
        }

        const rows = getRows?.() || [];
        const rest = rows.filter((row) => !drag.blockIds.has(row.id));
        const idIndex = new Map(rest.map((row, index) => [Number(row.id), index]));
        const rowElements = new Map();
        const measuredRows = Array.from(tbody.querySelectorAll('tr.lf-row'))
            .filter((row) => !drag.blockIds.has(Number(row.dataset.id)))
            .map((row) => {
                const id = Number(row.dataset.id);
                const rect = row.getBoundingClientRect();

                rowElements.set(id, row);

                return {
                    id,
                    top: rect.top,
                    midpoint: rect.top + rect.height / 2,
                    bottom: rect.bottom,
                };
            });

        drag.rest = rest;
        drag.idIndex = idIndex;
        drag.rowElements = rowElements;
        drag.measuredRows = measuredRows;
        geometryDirty = false;

        return true;
    };

    const firstMeasuredRowMatching = (predicate) => {
        const measuredRows = drag?.measuredRows || [];
        let low = 0;
        let high = measuredRows.length;

        while (low < high) {
            const middle = Math.floor((low + high) / 2);

            if (predicate(measuredRows[middle])) {
                high = middle;
            } else {
                low = middle + 1;
            }
        }

        return measuredRows[low] ?? null;
    };

    const processDragMove = (pointer) => {
        if (!drag || !pointer) {
            return;
        }

        moveGhost(pointer.clientX, pointer.clientY);

        if ((geometryDirty || !drag.measuredRows) && !refreshGeometry()) {
            return;
        }

        const rest = drag.rest || [];
        const draggedNode = drag.block[0];
        const hoverRow = firstMeasuredRowMatching((row) => row.bottom >= pointer.clientY);
        const hoverRowIndex = hoverRow && hoverRow.top <= pointer.clientY
            ? (drag.idIndex.get(hoverRow.id) ?? null)
            : null;
        const beforeRow = firstMeasuredRowMatching((row) => pointer.clientY < row.midpoint);
        const beforeId = beforeRow?.id ?? null;
        const insertIndex = beforeId === null
            ? rest.length
            : (drag.idIndex.get(beforeId) ?? rest.length);

        const resolved = resolveDropTarget({
            rest,
            draggedNode,
            insertIndex,
            hoverRowIndex,
            clientX: pointer.clientX,
            startX: drag.startX,
            startDepth: drag.startDepth,
            originalParentId: drag.originalParentId,
            indentPx: INDENT_PX,
        });

        drag.targetIndex = resolved.insertIndex;
        drag.targetDepth = resolved.targetDepth;
        drag.valid = resolved.valid;
        drag.highlightGroupId = resolved.highlightGroupId;
        updateState({ dragId: drag.id, nestDropGroupId: resolved.highlightGroupId });

        const targetGroup = resolved.highlightGroupId == null
            ? null
            : rest.find((row) => Number(row.id) === Number(resolved.highlightGroupId));
        const message = !resolved.valid
            ? 'Cannot place item here'
            : targetGroup
                ? `Move inside "${targetGroup.name}"`
                : 'Move to root';

        renderPlaceholder(
            resolved.beforeId,
            resolved.valid ? resolved.targetDepth : Math.max(1, resolved.targetDepth || 1),
            resolved.valid,
            message,
        );
    };

    const schedulePointerFrame = () => {
        if (pointerFrame !== null || !latestPointer) {
            return;
        }

        pointerFrame = window.requestAnimationFrame(() => {
            pointerFrame = null;
            processDragMove(latestPointer);
        });
    };

    const onDragMove = (event) => {
        if (!drag) {
            return;
        }

        latestPointer = {
            clientX: event.clientX,
            clientY: event.clientY,
        };
        schedulePointerFrame();
    };

    const markGeometryDirty = () => {
        if (!drag) {
            return;
        }

        geometryDirty = true;
        schedulePointerFrame();
    };

    const onDragUp = async () => {
        if (pointerFrame !== null) {
            window.cancelAnimationFrame(pointerFrame);
            pointerFrame = null;
            processDragMove(latestPointer);
        }

        const current = drag;
        cleanup();

        if (!current) {
            return;
        }

        if (!current.valid || current.targetDepth == null) {
            await onInvalidDrop?.(current);
            return;
        }

        const rows = getRows?.() || [];
        const rest = rows.filter((row) => !current.blockIds.has(row.id));
        const at = Math.max(0, Math.min(current.targetIndex, rest.length));
        const parent = parentAt(rest, at, current.targetDepth);

        if (!canPlace(current.block[0], parent, current.originalParentId)) {
            await onInvalidDrop?.(current);
            return;
        }

        const delta = current.targetDepth - current.startDepth;
        const moved = current.block.map((row) => ({
            ...row,
            depth: Math.max(1, row.depth + delta),
        }));

        rest.splice(at, 0, ...moved);

        for (let index = 0; index < rest.length; index++) {
            const previousDepth = index === 0 ? 0 : rest[index - 1].depth;
            rest[index].depth = Math.max(1, Math.min(rest[index].depth, previousDepth + 1));
        }

        const structureChanged = rest.length !== rows.length
            || rest.some((row, index) => {
                const original = rows[index];

                return !original
                    || Number(row.id) !== Number(original.id)
                    || Number(row.depth) !== Number(original.depth);
            });

        if (!structureChanged) {
            return;
        }

        await onDrop?.({
            rows: rest,
            drag: current,
            parent,
            position: at,
        });
    };

    const onHandleDown = (event, id) => {
        if (!getEditable?.()) {
            return;
        }

        if (event.button != null && event.button !== 0) {
            return;
        }

        event.preventDefault();

        const rows = getRows?.() || [];
        const index = rows.findIndex((row) => Number(row.id) === Number(id));

        if (index === -1) {
            return;
        }

        const node = rows[index];
        let end = index + 1;

        while (end < rows.length && rows[end].depth > node.depth) {
            end++;
        }

        const block = rows.slice(index, end);
        const blockIds = new Set(block.map((row) => row.id));
        let originalParentId = null;

        for (let current = index - 1; current >= 0; current--) {
            if (rows[current].depth === node.depth - 1) {
                originalParentId = rows[current].id;
                break;
            }

            if (rows[current].depth < node.depth - 1) {
                break;
            }
        }

        drag = {
            id,
            block,
            blockIds,
            startX: event.clientX,
            startDepth: node.depth,
            targetIndex: index,
            targetDepth: node.depth,
            originalParentId,
            originalIndex: index,
            valid: true,
            pointerId: event.pointerId,
        };

        refreshGeometry();

        const ghost = getGhost?.();

        if (ghost) {
            ghost.textContent = `${node.item_type === 'group' ? '▦ ' : ''}${node.name}${block.length > 1 ? `  (+${block.length - 1})` : ''}`;
            moveGhost(event.clientX, event.clientY);
        }

        updateState({ dragId: id, nestDropGroupId: null });
        moveListener = onDragMove;
        upListener = onDragUp;
        cancelListener = onDragUp;
        scrollListener = markGeometryDirty;
        resizeListener = markGeometryDirty;
        window.addEventListener('pointermove', moveListener);
        window.addEventListener('pointerup', upListener, { once: true });
        window.addEventListener('pointercancel', cancelListener, { once: true });
        window.addEventListener('scroll', scrollListener, true);
        window.addEventListener('resize', resizeListener);
    };

    return {
        onHandleDown,
        destroy: cleanup,
    };
}
