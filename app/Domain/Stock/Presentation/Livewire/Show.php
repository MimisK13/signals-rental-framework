<?php

declare(strict_types=1);

namespace App\Domain\Stock\Presentation\Livewire;

use App\Domain\Stock\Actions\AdjustStockLevel;
use App\Domain\Stock\Actions\ReverseStockTransaction;
use App\Domain\Stock\Data\AdjustStockLevelData;
use App\Domain\Stock\Enums\TransactionType;
use App\Domain\Stock\Models\StockLevel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
    public StockLevel $stockLevel;

    public int $transactionType = 2;

    public string $transactionQuantity = '1';

    public string $transactionDescription = '';

    public ?int $deletingTransactionId = null;

    public string $reversalReason = '';

    public function mount(StockLevel $stockLevel): void
    {
        $this->stockLevel = $stockLevel->load(['product', 'store', 'member', 'stockTransactions.actor', 'stockTransactions.reversal']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->stockLevel->item_name);
    }

    public function isSerialised(): bool
    {
        return $this->stockLevel->isSerialised();
    }

    public function openTransactionModal(): void
    {
        $this->resetValidation();
        $this->transactionType = TransactionType::Increase->value;
        $this->transactionQuantity = '1';
        $this->transactionDescription = '';
    }

    public function addTransaction(): void
    {
        $this->validate([
            'transactionQuantity' => ['required', 'integer', 'min:1'],
            'transactionType' => ['required', 'integer', Rule::in(TransactionType::adjustmentValues())],
            'transactionDescription' => ['required', 'string', 'max:1000'],
        ], [
            'transactionQuantity.integer' => 'Quantity must be a whole number.',
            'transactionDescription.required' => 'A reason is required for a stock adjustment.',
        ]);

        try {
            $dto = AdjustStockLevelData::from([
                'transaction_type' => $this->transactionType,
                'quantity' => $this->transactionQuantity,
                'reason' => $this->transactionDescription,
            ]);

            (new AdjustStockLevel)($this->stockLevel, $dto);

            $this->stockLevel->refresh();
            $this->stockLevel->load(['product', 'store', 'member', 'stockTransactions.actor', 'stockTransactions.reversal']);
            $this->transactionQuantity = '1';
            $this->transactionDescription = '';
            $this->dispatch('transaction-saved');
        } catch (AuthorizationException) {
            $this->addError('transactionQuantity', 'You do not have permission to adjust stock.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function previewQuantity(): string
    {
        $current = (float) $this->stockLevel->quantity_held;
        $quantity = (float) $this->transactionQuantity;
        $sign = TransactionType::tryFrom((int) $this->transactionType)?->quantitySign() ?? 1;

        return number_format($current + ($quantity * $sign), 2, '.', '');
    }

    public function previewQuantityMove(): string
    {
        $quantity = (float) $this->transactionQuantity;
        $sign = TransactionType::tryFrom((int) $this->transactionType)?->quantitySign() ?? 1;

        return number_format($quantity * $sign, 2, '.', '');
    }

    public function confirmDeleteTransaction(int $transactionId): void
    {
        $this->deletingTransactionId = $transactionId;
        $this->reversalReason = '';
        $this->resetValidation();
    }

    public function deleteTransaction(): void
    {
        if ($this->deletingTransactionId === null) {
            return;
        }

        try {
            $transaction = $this->stockLevel->stockTransactions()->findOrFail($this->deletingTransactionId);
            $this->validate([
                'reversalReason' => ['required', 'string', 'max:1000'],
            ]);

            (new ReverseStockTransaction)($transaction, $this->reversalReason);

            $this->stockLevel->refresh();
            $this->stockLevel->load(['product', 'store', 'member', 'stockTransactions.actor', 'stockTransactions.reversal']);
        } catch (AuthorizationException) {
            $this->addError('transactionQuantity', 'You do not have permission to adjust stock.');
        }

        $this->deletingTransactionId = null;
        $this->reversalReason = '';
        $this->dispatch('transaction-deleted');
    }

    public function render(): ViewContract
    {
        return view('stock::show');
    }
}
