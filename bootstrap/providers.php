<?php

use App\Domain\Activities\ActivitiesServiceProvider;
use App\Domain\Administration\AdministrationServiceProvider;
use App\Domain\API\APIServiceProvider;
use App\Domain\Assets\AssetsServiceProvider;
use App\Domain\Attachments\AttachmentsServiceProvider;
use App\Domain\AuditLog\AuditLogServiceProvider;
use App\Domain\Auth\AuthServiceProvider;
use App\Domain\Availability\AvailabilityServiceProvider;
use App\Domain\Communications\CommunicationsServiceProvider;
use App\Domain\Crew\CrewServiceProvider;
use App\Domain\Currency\CurrencyServiceProvider;
use App\Domain\CustomFields\CustomFieldsServiceProvider;
use App\Domain\CustomViews\CustomViewsServiceProvider;
use App\Domain\Dashboard\DashboardServiceProvider;
use App\Domain\Documentation\DocumentationServiceProvider;
use App\Domain\Documents\DocumentsServiceProvider;
use App\Domain\Invoices\InvoicesServiceProvider;
use App\Domain\Lists\ListsServiceProvider;
use App\Domain\Members\MembersServiceProvider;
use App\Domain\Notifications\NotificationsServiceProvider;
use App\Domain\Numbering\NumberingServiceProvider;
use App\Domain\Pricing\PricingServiceProvider;
use App\Domain\Products\ProductsServiceProvider;
use App\Domain\Projects\ProjectsServiceProvider;
use App\Domain\PurchaseOrders\PurchaseOrdersServiceProvider;
use App\Domain\Quotations\QuotationsServiceProvider;
use App\Domain\Rates\RatesServiceProvider;
use App\Domain\ReferenceData\ReferenceDataServiceProvider;
use App\Domain\Search\SearchServiceProvider;
use App\Domain\Settings\SettingsServiceProvider;
use App\Domain\Setup\SetupServiceProvider;
use App\Domain\Stock\StockServiceProvider;
use App\Domain\Stores\StoresServiceProvider;
use App\Domain\SupplierBills\SupplierBillsServiceProvider;
use App\Domain\SupplierCosts\SupplierCostsServiceProvider;
use App\Domain\System\SystemServiceProvider;
use App\Domain\Tax\TaxServiceProvider;
use App\Domain\VirtualStock\VirtualStockServiceProvider;
use App\Domain\Warehouse\WarehouseServiceProvider;
use App\Domain\Webhooks\WebhooksServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\VoltServiceProvider;
use Ui\Dense\DenseServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    AuthServiceProvider::class,
    AdministrationServiceProvider::class,
    APIServiceProvider::class,
    AttachmentsServiceProvider::class,
    AvailabilityServiceProvider::class,
    AssetsServiceProvider::class,
    CrewServiceProvider::class,
    CommunicationsServiceProvider::class,
    CustomViewsServiceProvider::class,
    DashboardServiceProvider::class,
    DocumentationServiceProvider::class,
    CustomFieldsServiceProvider::class,
    DocumentsServiceProvider::class,
    ActivitiesServiceProvider::class,
    AuditLogServiceProvider::class,
    StockServiceProvider::class,
    StoresServiceProvider::class,
    SystemServiceProvider::class,
    CurrencyServiceProvider::class,
    ListsServiceProvider::class,
    MembersServiceProvider::class,
    NotificationsServiceProvider::class,
    NumberingServiceProvider::class,
    InvoicesServiceProvider::class,
    ProjectsServiceProvider::class,
    ProductsServiceProvider::class,
    PricingServiceProvider::class,
    PurchaseOrdersServiceProvider::class,
    ReferenceDataServiceProvider::class,
    SearchServiceProvider::class,
    QuotationsServiceProvider::class,
    RatesServiceProvider::class,
    SetupServiceProvider::class,
    SupplierCostsServiceProvider::class,
    SupplierBillsServiceProvider::class,
    TaxServiceProvider::class,
    WarehouseServiceProvider::class,
    WebhooksServiceProvider::class,
    VirtualStockServiceProvider::class,
    VoltServiceProvider::class,
    DenseServiceProvider::class,
];
