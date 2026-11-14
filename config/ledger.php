<?php

return [

    /*
    | Number of shards for each system ledger account (escrow holding, platform revenue, gateway clearing).
    | One shared row would serialise every money operation on a single lock; shards spread that contention.
    | This number may only grow. Run `php artisan ledger:ensure-shards` after raising it. Existing escrows
    | store their own shard, so raising it never remaps funds that are already held.
    */
    'shards' => (int) env('LEDGER_SHARDS', 8),

];
