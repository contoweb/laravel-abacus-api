<?php

namespace Contoweb\AbacusApi\Models\v1;

use Contoweb\AbacusApi\Models\AbacusModel;

class Stock extends AbacusModel
{
    protected static string $resource = 'Stocks';
    protected static string|array $primaryKey = ['ProductId', 'VariantId', 'StockLocation', 'StockArea'];
}