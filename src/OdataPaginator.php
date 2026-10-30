<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Exceptions\AbacusAuthenticationException;
use Contoweb\AbacusApi\Exceptions\AbacusRateLimitException;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

/**
 * @template TModel of AbacusModel
 */
class OdataPaginator
{
    /** @var Collection<int, TModel> */
    private Collection $items;

    private ?string $nextLink;

    private AbacusODataClient $client;

    /** @var class-string<TModel> */
    private string $modelClass;

    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(array $items, AbacusODataClient $client, string $modelClass, ?string $nextLink = null)
    {
        $this->items = new Collection;
        $this->client = $client;
        $this->nextLink = $nextLink;
        $this->modelClass = $modelClass;
        $this->addItems($items);
    }

    /**
     * Returns all currently loaded items
     *
     * @return Collection<int, TModel>
     */
    public function items(): Collection
    {
        return $this->items;
    }

    private function addItems(array $items): void
    {
        $this->items = $this->items->merge(collect($items)->map(fn ($item) => new $this->modelClass($item)));
    }

    /**
     * Checks if more pages are available
     */
    public function hasMorePages(): bool
    {
        return $this->nextLink !== null;
    }

    /**
     * Loads the next link page and appends it to the items collection
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function nextPage(): void
    {
        if ($this->hasMorePages()) {
            $response = $this->client
                ->getNextLink($this->nextLink)
                ->json();

            if (isset($response['@odata.nextLink'])) {
                $this->nextLink = $response['@odata.nextLink'];
            } else {
                $this->nextLink = null;
            }

            if (isset($response['value'])) {
                $this->addItems($response['value']);
            }
        }
    }
}
