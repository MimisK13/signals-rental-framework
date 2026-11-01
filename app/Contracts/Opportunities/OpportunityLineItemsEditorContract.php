<?php

namespace App\Contracts\Opportunities;

/**
 * Server contract surface for the production local-first line-items editor Volt
 * component. Implemented by the anonymous Volt class so tests and static analysis
 * can type-hint against it.
 */
interface OpportunityLineItemsEditorContract
{
    public function treeRevision(): int;

    public function treeRevisionToken(): string;

    /**
     * @return array{charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    public function totalsSnapshot(): array;

    /**
     * @return array{tree: array<int, array<string, mixed>>, revision: string, charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    public function serverTree(): array;

    /**
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array{stale: bool, revision: string, revision_drift?: bool, base_revision?: string, server_revision_before?: string}
     */
    public function persistTree(array $nodes, int $baseRevision = 0): array;

    /**
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  array<int, int>  $pendingLocalIds
     *                                            Implementations may return a lightweight acknowledgement without `tree`
     *                                            when the client revision is current. Experimental implementations may
     *                                            also accept optional synchronization flags after the shared arguments.
     * @return array{changed?: bool, stale: bool, revision: string, tree?: array<int, array<string, mixed>>, conflicts?: array<int, string>, cache_token: string, charge_total: int, deal_total: int|null, has_deal_price: bool, collapsed_section_ids?: array<int, int>}
     */
    public function pullTree(int $baseRevision, array $localRows = [], array $pendingLocalIds = []): array;
}
