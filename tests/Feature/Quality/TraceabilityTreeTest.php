<?php

use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Services\TraceabilityTree;

function treeTones(array $node): array
{
    return array_values(array_unique([
        $node['tone'],
        ...collect($node['children'])->flatMap(fn (array $child) => treeTones($child))->all(),
    ]));
}

it('leaves inspection reports out of the purchase history tree', function () {
    $this->seed(QualityDatabaseSeeder::class);

    $lot = IncomingLot::query()
        ->with('part')
        ->whereHas('reports')
        ->whereNotNull('purchase_invoice_line_id')
        ->firstOrFail();
    $tree = new TraceabilityTree;

    expect(treeTones($tree->forPart($lot->part)))->toContain('po', 'pi', 'lot', 'report')
        ->and(treeTones($tree->forLot($lot)))->toContain('lot', 'pi', 'po', 'report');

    $partTones = treeTones($tree->forPart($lot->part, true, false));
    $lotTones = treeTones($tree->forLot($lot, true, false));

    expect($partTones)->toContain('po', 'pi', 'lot')->not->toContain('report')
        ->and($lotTones)->toContain('lot', 'pi', 'po')->not->toContain('report');
});
