<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData\Presentation\Http\Controllers\Api\V1;

use App\Domain\ReferenceData\Data\CountryApiData;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\ReferenceData\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'code',
        'code3',
        'currency_code',
        'is_active',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'code',
    ];

    /**
     * List countries with filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('static-data.view', 'countries:read');

        $query = Country::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $countries = $paginator->getCollection()->map(
            fn (Country $country): array => CountryApiData::fromModel($country)->toArray()
        )->all();

        return $this->respondWithCollection($countries, 'countries', $paginator);
    }

    /**
     * Show a single country.
     */
    public function show(Country $country): JsonResponse
    {
        $this->authorizeApi('static-data.view', 'countries:read');

        return $this->respondWith(
            CountryApiData::fromModel($country)->toArray(),
            'country',
        );
    }
}
