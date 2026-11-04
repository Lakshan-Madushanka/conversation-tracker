<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Splitting one sitting between the exercise kinds that have something to
 * offer, by their target shares.
 *
 * The shares are renormalised over the kinds that are actually available, so a
 * kind with nothing to show never takes a slot. The total is shared with the
 * largest-remainder rule, so the last slot goes to the kind the shares leave
 * furthest above its whole number, and the counts always add up to the
 * sitting's size.
 *
 * When the sitting has room for every available kind, each kind keeps at least
 * one slot: a kind the shares would have left at zero takes its slot from the
 * kind holding the most. That way a kind with only one item still earns its
 * step instead of disappearing from the mix.
 */
final class ReviewAllocation
{
    /**
     * @param  list<string>  $active  the kinds that have candidates
     * @param  array<string, float>  $shares  the target share per kind key
     * @return array<string, int> count per active kind
     */
    public static function quotas(array $active, int $size, array $shares): array
    {
        $active = array_values(array_unique($active));
        $count = count($active);

        if ($count === 0) {
            return [];
        }

        $quotas = array_fill_keys($active, 0);
        $size = max(0, $size);

        if ($size === 0) {
            return $quotas;
        }

        $weights = [];
        $total = 0.0;

        foreach ($active as $key) {
            $weight = $shares[$key] ?? 1.0;
            $weights[$key] = $weight > 0 ? $weight : 0.0;
            $total += $weights[$key];
        }

        if ($total <= 0.0) {
            foreach ($active as $key) {
                $weights[$key] = 1.0;
            }

            $total = (float) $count;
        }

        $fractions = [];
        $assigned = 0;

        foreach ($active as $key) {
            $exact = $size * $weights[$key] / $total;
            $base = (int) floor($exact);
            $quotas[$key] = $base;
            $fractions[$key] = $exact - $base;
            $assigned += $base;
        }

        while ($assigned < $size) {
            $next = null;

            foreach ($active as $key) {
                if ($next === null || $fractions[$key] > $fractions[$next]) {
                    $next = $key;
                }
            }

            $quotas[$next] += 1;
            $fractions[$next] = -1.0;
            $assigned += 1;
        }

        if ($size >= $count) {
            foreach ($active as $key) {
                if ($quotas[$key] > 0) {
                    continue;
                }

                $donor = null;

                foreach ($active as $candidate) {
                    if ($quotas[$candidate] < 2) {
                        continue;
                    }

                    if ($donor === null || $quotas[$candidate] > $quotas[$donor]) {
                        $donor = $candidate;
                    }
                }

                if ($donor === null) {
                    break;
                }

                $quotas[$donor] -= 1;
                $quotas[$key] += 1;
            }
        }

        return $quotas;
    }
}
