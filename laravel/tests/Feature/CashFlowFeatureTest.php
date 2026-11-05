<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\CashFlows\Model\CashFlowImportSession;
use App\Feature\CashFlows\Model\MarketPairCashFlow;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketTypes\Model\MarketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_pair_cash_flow_events_support_crud_and_pair_filtering(): void
    {
        [$pair, $otherPair] = $this->createPairs();

        $this->post('/workbench/cash-flows', $this->cashFlowInput($pair->id))
            ->assertRedirect();
        $cashFlow = MarketPairCashFlow::query()->sole();

        $this->assertSame($pair->id, $cashFlow->market_pair_id);
        $this->assertSame('0.500000000000', $cashFlow->amount_per_unit);
        $this->assertSame('2026-03-01 09:00:00', $cashFlow->available_at->format('Y-m-d H:i:s'));
        $this->get('/workbench/cash-flows')
            ->assertOk()
            ->assertSee('AAPL/USD')
            ->assertSee('Dividend');
        $this->get('/workbench/cash-flows?market_pair_id='.$otherPair->id)
            ->assertOk()
            ->assertDontSee('2026-03-01 09:00:00')
            ->assertSee('No cash flow events found.');
        $this->get("/workbench/cash-flows/{$cashFlow->id}")
            ->assertOk()
            ->assertSee('EX-DATE');

        $updated = $this->cashFlowInput($pair->id);
        $updated['amount_per_unit'] = '0.75';
        $updated['status'] = 'paid';
        $this->put("/workbench/cash-flows/{$cashFlow->id}", $updated)
            ->assertRedirect("/workbench/cash-flows/{$cashFlow->id}");
        $this->assertDatabaseHas('market_pair_cash_flows', [
            'id' => $cashFlow->id,
            'amount_per_unit' => '0.750000000000',
            'status' => 'paid',
        ]);

        $this->delete("/workbench/cash-flows/{$cashFlow->id}")
            ->assertRedirect('/workbench/cash-flows');
        $this->assertDatabaseMissing('market_pair_cash_flows', ['id' => $cashFlow->id]);
    }

    public function test_cash_flow_receiver_imports_and_updates_events_for_its_market_pair_only(): void
    {
        [$pair, $otherPair] = $this->createPairs();
        $this->get('/workbench/data-import')
            ->assertOk()
            ->assertSee('ASSET CASH FLOW · DISTRIBUTIONS')
            ->assertSee('/api/data-import/cash-flows/events');
        $this->post('/workbench/data-import/cash-flow-sessions', ['market_pair_id' => $pair->id])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHas('cash_flow_import_token');

        $session = CashFlowImportSession::query()->sole();
        $token = (string) session('cash_flow_import_token');
        $this->assertSame(hash('sha256', $token), $session->token_hash);

        $event = $this->cashFlowEvent();
        $this->withToken($token)->postJson('/api/data-import/cash-flows/events', [
            'events' => [$event],
        ])->assertOk()->assertExactJson(['imported' => 1, 'inserted' => 1, 'updated' => 0]);

        $cashFlow = MarketPairCashFlow::query()->sole();
        $this->assertSame($pair->id, $cashFlow->market_pair_id);
        $this->assertSame('0.500000000000', $cashFlow->amount_per_unit);
        $this->assertSame('DIV-2026-001', $cashFlow->external_id);

        $event['amount_per_unit'] = 0.65;
        $event['status'] = 'paid';
        $this->withToken($token)->postJson('/api/data-import/cash-flows/events', [
            'events' => [$event],
        ])->assertOk()->assertExactJson(['imported' => 1, 'inserted' => 0, 'updated' => 1]);
        $this->assertSame(1, MarketPairCashFlow::query()->count());
        $this->assertDatabaseHas('market_pair_cash_flows', [
            'market_pair_id' => $pair->id,
            'amount_per_unit' => '0.650000000000',
            'status' => 'paid',
        ]);
        $this->assertDatabaseMissing('market_pair_cash_flows', ['market_pair_id' => $otherPair->id]);

        $this->post("/workbench/data-import/cash-flow-sessions/{$pair->id}/{$session->id}/stop")
            ->assertRedirect('/workbench/data-import');
        $this->withToken($token)->postJson('/api/data-import/cash-flows/events', [
            'events' => [$event],
        ])->assertUnauthorized();
    }

    public function test_cash_flow_receiver_rejects_bad_or_duplicate_events_and_duplicate_active_sessions(): void
    {
        [$pair] = $this->createPairs();
        $this->post('/workbench/data-import/cash-flow-sessions', ['market_pair_id' => $pair->id])
            ->assertRedirect();
        $token = (string) session('cash_flow_import_token');

        $this->from('/workbench/data-import')
            ->post('/workbench/data-import/cash-flow-sessions', ['market_pair_id' => $pair->id])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHasErrors('market_pair_id');

        $invalid = $this->cashFlowEvent();
        $invalid['amount_per_unit'] = 0;
        $this->withToken($token)->postJson('/api/data-import/cash-flows/events', [
            'events' => [$invalid],
        ])->assertUnprocessable()->assertJsonValidationErrors('events.0.amount_per_unit');

        $event = $this->cashFlowEvent();
        $this->withToken($token)->postJson('/api/data-import/cash-flows/events', [
            'events' => [$event, $event],
        ])->assertUnprocessable()->assertJsonValidationErrors('events.1.external_id');
        $this->assertDatabaseCount('market_pair_cash_flows', 0);

        $this->withToken('invalid')->postJson('/api/data-import/cash-flows/events', [
            'events' => [$this->cashFlowEvent()],
        ])->assertUnauthorized();
    }

    public function test_market_pair_with_cash_flow_history_or_import_sessions_cannot_be_deleted(): void
    {
        [$pair] = $this->createPairs();
        MarketPairCashFlow::query()->create($this->cashFlowInput($pair->id));

        $this->from('/workbench/market-pairs')
            ->delete("/workbench/market-pairs/{$pair->id}")
            ->assertRedirect('/workbench/market-pairs')
            ->assertSessionHasErrors('market_pair');
        $this->assertDatabaseHas('market_pair', ['id' => $pair->id]);

        MarketPairCashFlow::query()->delete();
        $this->post('/workbench/data-import/cash-flow-sessions', ['market_pair_id' => $pair->id])
            ->assertRedirect();
        $this->from('/workbench/market-pairs')
            ->delete("/workbench/market-pairs/{$pair->id}")
            ->assertRedirect('/workbench/market-pairs')
            ->assertSessionHasErrors('market_pair');
    }

    /** @return array{MarketPair, MarketPair} */
    private function createPairs(): array
    {
        $type = MarketType::query()->create(['name' => 'Equities']);
        $pair = MarketPair::query()->create([
            'market_type_id' => $type->id,
            'base' => 'AAPL',
            'quote' => 'USD',
        ]);
        $otherPair = MarketPair::query()->create([
            'market_type_id' => $type->id,
            'base' => 'MSFT',
            'quote' => 'USD',
        ]);

        return [$pair, $otherPair];
    }

    /** @return array<string, mixed> */
    private function cashFlowInput(int $pairId): array
    {
        return [
            'market_pair_id' => $pairId,
            'event_type' => 'dividend',
            'amount_per_unit' => '0.500000000000',
            'announced_at' => '2026-03-01T09:00',
            'available_at' => '2026-03-01T09:00',
            'ex_date' => '2026-03-20T00:00',
            'record_date' => '2026-03-21T00:00',
            'payable_at' => '2026-04-05T00:00',
            'status' => 'confirmed',
            'source' => 'provider',
            'external_id' => 'DIV-2026-001',
        ];
    }

    /** @return array<string, mixed> */
    private function cashFlowEvent(): array
    {
        return [
            'event_type' => 'dividend',
            'amount_per_unit' => 0.50,
            'announced_at' => '2026-03-01 09:00:00',
            'available_at' => '2026-03-01 09:00:00',
            'ex_date' => '2026-03-20 00:00:00',
            'record_date' => '2026-03-21 00:00:00',
            'payable_at' => '2026-04-05 00:00:00',
            'status' => 'confirmed',
            'source' => 'provider',
            'external_id' => 'DIV-2026-001',
        ];
    }
}
