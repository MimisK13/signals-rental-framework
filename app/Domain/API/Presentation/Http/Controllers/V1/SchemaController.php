<?php

namespace App\Domain\API\Presentation\Http\Controllers\V1;

use App\Domain\Activities\Models\Activity;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Support\Attributes\PriceWeight;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use App\Domain\CustomViews\Models\CustomView;
use App\Domain\Members\Models\Address;
use App\Domain\Members\Models\Email;
use App\Domain\Members\Models\Link;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\Phone;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\ReferenceData\Models\Country;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Models\StockTransaction;
use App\Domain\Stores\Models\Store;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Tax\Schemas\DocumentTaxGroupSchema;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Webhooks\Models\Webhook;
use App\Models\User;
use App\Services\SchemaRegistry;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SchemaController extends Controller
{
    /**
     * Map of URL-friendly model names to fully qualified class names.
     *
     * @var array<string, class-string>
     */
    private const MODEL_MAP = [
        'members' => Member::class,
        'stores' => Store::class,
        'addresses' => Address::class,
        'countries' => Country::class,
        'currencies' => Currency::class,
        'exchange_rates' => ExchangeRate::class,
        'emails' => Email::class,
        'phones' => Phone::class,
        'links' => Link::class,
        'attachments' => Attachment::class,
        'users' => User::class,
        'action_logs' => ActionLog::class,
        'webhooks' => Webhook::class,
        'custom_views' => CustomView::class,
        'tax_rates' => TaxRate::class,
        'tax_rules' => TaxRule::class,
        'products' => Product::class,
        'product_groups' => ProductGroup::class,
        'stock_levels' => StockLevel::class,
        'stock_transactions' => StockTransaction::class,
        'activities' => Activity::class,
        'rate_definitions' => RateDefinition::class,
        'product_rates' => ProductRate::class,
        'opportunities' => Opportunity::class,
        'opportunity_items' => OpportunityItem::class,
        'opportunity_costs' => OpportunityCost::class,
        'opportunity_versions' => OpportunityVersion::class,
        'containers' => Container::class,
        'shortage_resolutions' => ShortageResolution::class,
        'document_tax_groups' => DocumentTaxGroupSchema::class,
    ];

    /**
     * List all available schema model names.
     */
    #[PriceWeight(0)]
    public function index(): JsonResponse
    {
        $this->authorizeApi('schema.read', 'schema:read');

        return response()->json([
            'schemas' => array_keys(self::MODEL_MAP),
        ]);
    }

    /**
     * Get the full field schema for a model.
     *
     * @param  string  $model  URL-friendly model name (e.g. 'members', 'stores')
     */
    #[ApiResponse(200, 'Model field schema', type: 'array{model: string, model_class: string, fields: array<string, array{name: string, label: string, type: string, required: bool, searchable: bool, filterable: bool, sortable: bool}>}')]
    #[ApiResponse(404, 'Unknown model', type: 'array{message: string, available: list<string>}')]
    #[PriceWeight(0)]
    public function show(string $model, SchemaRegistry $registry): JsonResponse
    {
        $this->authorizeApi('schema.read', 'schema:read');

        // Accept either the canonical plural key or its singular form.
        $key = isset(self::MODEL_MAP[$model]) ? $model : Str::plural($model);
        $modelClass = self::MODEL_MAP[$key] ?? null;

        if ($modelClass === null) {
            return response()->json([
                'message' => "Unknown schema model: {$model}",
                'available' => array_keys(self::MODEL_MAP),
            ], 404);
        }

        $fields = $registry->resolve($modelClass);

        $schema = [];
        foreach ($fields as $name => $definition) {
            $schema[$name] = $definition->toArray();
        }

        return response()->json([
            'model' => $key,
            'model_class' => Str::afterLast($modelClass, '\\'),
            'fields' => $schema,
        ]);
    }
}
