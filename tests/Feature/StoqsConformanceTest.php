<?php

namespace Tests\Feature;

use App\Services\Discount\StoqsEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * StoqS's discount conformance fixtures (copied from StoqS tests/fixtures/discount-conformance,
 * unchanged) run against this shop's port of the engine (App\Services\Discount\StoqsEngine),
 * compared the way StoqS's own runner compares them (tests/discount-conformance-test.php):
 * the pinned values, the three invariants every result holds, and split-cart invariance.
 */
class StoqsConformanceTest extends TestCase
{
    private const DIR = __DIR__.'/../Fixtures/discount-conformance';

    /** @return array<string, array{0: array}> */
    public static function fixtures(): array
    {
        $out = [];
        foreach (glob(self::DIR.'/c*.json') as $f) {
            $j = json_decode((string) file_get_contents($f), true);
            $out[basename($f, '.json')] = [$j];
        }

        return $out;
    }

    public function test_every_fixture_is_here_and_runs_at_this_schema(): void
    {
        $all = self::fixtures();
        $this->assertGreaterThanOrEqual(35, count($all));
        $this->assertSame(3, StoqsEngine::SCHEMA);
        foreach ($all as $id => [$j]) {
            $this->assertIsArray($j, "$id is valid JSON");
            $this->assertSame($id, $j['id']);
            $this->assertLessThanOrEqual(StoqsEngine::SCHEMA, $j['schema'], "$id runs at schema ".StoqsEngine::SCHEMA);
        }
    }

    #[DataProvider('fixtures')]
    public function test_fixture(array $j): void
    {
        $ev = StoqsEngine::evaluate($j['rules'], $j['codes'], $j['lines'], $j['opts']);
        $bad = self::diff($ev, $j['expect']);
        $this->assertSame([], $bad, $j['id'].' '.$j['title'].': '.implode('; ', $bad));
    }

    public function test_split_cart_invariance(): void
    {
        $by = [];
        foreach (self::fixtures() as $id => [$j]) {
            $by[$id] = $j;
        }
        $sums = 0;
        foreach ($by as $j) {
            if (empty($j['equals_sum_of'])) {
                continue;
            }
            $sums++;
            $parts = 0;
            foreach ((array) $j['equals_sum_of'] as $id) {
                $this->assertArrayHasKey($id, $by, "{$j['id']} names $id");
                $f = $by[$id];
                $parts += StoqsEngine::evaluate($f['rules'], $f['codes'], $f['lines'], $f['opts'])['discount'];
            }
            $whole = StoqsEngine::evaluate($j['rules'], $j['codes'], $j['lines'], $j['opts'])['discount'];
            $this->assertSame($parts, $whole, "{$j['id']} equals the sum of ".implode(' + ', (array) $j['equals_sum_of']));
        }
        $this->assertGreaterThanOrEqual(1, $sums);
    }

    /** Every difference between the result and what the fixture pins (StoqS's conformance_diff). */
    private static function diff(array $ev, array $x): array
    {
        $bad = [];
        foreach (['subtotal', 'discountable_subtotal', 'cap', 'discount', 'total', 'shipping_free', 'cap_basis'] as $k) {
            if (! array_key_exists($k, $x)) {
                continue;
            }
            $got = $ev[$k] ?? null;
            if (! self::same($got, $x[$k])) {
                $bad[] = "$k ".json_encode($got).' ≠ '.json_encode($x[$k]);
            }
        }
        if (array_key_exists('rules_applied', $x)) {
            $got = array_values(array_map(fn ($r) => $r['rule_id'], array_filter($ev['rules'], fn ($r) => $r['applied'] && $r['amount'] > 0)));
            $want = array_map('intval', $x['rules_applied']);
            sort($got);
            sort($want);
            if ($got !== $want) {
                $bad[] = 'rules_applied '.json_encode($got).' ≠ '.json_encode($want);
            }
        }
        foreach (['codes', 'lines'] as $list) {
            if (! array_key_exists($list, $x)) {
                continue;
            }
            if (count($ev[$list]) !== count($x[$list])) {
                $bad[] = "$list: ".count($ev[$list]).' ≠ '.count($x[$list]);
            }
            foreach ($x[$list] as $i => $want) {
                foreach ($want as $k => $v) {
                    $got = $ev[$list][$i][$k] ?? null;
                    if (! self::same($got, $v)) {
                        $bad[] = "{$list}[$i].$k ".json_encode($got).' ≠ '.json_encode($v);
                    }
                }
            }
        }
        $sum = 0;
        foreach ($ev['lines'] as $i => $l) {
            $sum += $l['discount'];
            if ($l['discount'] > $l['room'] + 1) {
                $bad[] = "lines[$i] over its room";
            }
            $p = 0;
            foreach ($l['parts'] as $part) {
                $p += $part['amount'];
            }
            if ($p !== $l['discount']) {
                $bad[] = "lines[$i] parts do not add up";
            }
        }
        if ($sum !== $ev['discount']) {
            $bad[] = "the lines add up to $sum, not {$ev['discount']}";
        }

        return $bad;
    }

    private static function same($got, $want): bool
    {
        if (is_int($want) || is_float($want)) {
            return (is_int($got) || is_float($got)) && abs((float) $got - (float) $want) < 1e-6;
        }

        return $got === $want;
    }
}
