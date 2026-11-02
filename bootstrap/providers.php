<?php

use App\Domain\Activities\ActivitiesServiceProvider;
use App\Domain\Assets\AssetsServiceProvider;
use App\Domain\Attachments\AttachmentsServiceProvider;
use App\Domain\Crew\CrewServiceProvider;
use App\Domain\Currency\CurrencyServiceProvider;
use App\Domain\CustomFields\CustomFieldsServiceProvider;
use App\Domain\CustomViews\CustomViewsServiceProvider;
use App\Domain\Documents\DocumentsServiceProvider;
use App\Domain\Invoices\InvoicesServiceProvider;
use App\Domain\Lists\ListsServiceProvider;
use App\Domain\Projects\ProjectsServiceProvider;
use App\Domain\PurchaseOrders\PurchaseOrdersServiceProvider;
use App\Domain\Quotations\QuotationsServiceProvider;
use App\Domain\Rates\RatesServiceProvider;
use App\Domain\Repairs\RepairsServiceProvider;
use App\Domain\Stock\StockServiceProvider;
use App\Domain\Stocktakes\StocktakesServiceProvider;
use App\Domain\SupplierBills\SupplierBillsServiceProvider;
use App\Domain\SupplierCosts\SupplierCostsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\VoltServiceProvider;
use Ui\Dense\DenseServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    AttachmentsServiceProvider::class,
    AssetsServiceProvider::class,
    CrewServiceProvider::class,
    CustomViewsServiceProvider::class,
    CustomFieldsServiceProvider::class,
    DocumentsServiceProvider::class,
    ActivitiesServiceProvider::class,
    StocktakesServiceProvider::class,
    StockServiceProvider::class,
    CurrencyServiceProvider::class,
    ListsServiceProvider::class,
    InvoicesServiceProvider::class,
    ProjectsServiceProvider::class,
    PurchaseOrdersServiceProvider::class,
    QuotationsServiceProvider::class,
    RatesServiceProvider::class,
    RepairsServiceProvider::class,
    SupplierCostsServiceProvider::class,
    SupplierBillsServiceProvider::class,
    VoltServiceProvider::class,
    DenseServiceProvider::class,
];
