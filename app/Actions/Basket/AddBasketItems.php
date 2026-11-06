<?php

declare(strict_types=1);

namespace App\Actions\Basket;

use App\Enums\FeedbackType;
use App\Models\BasketItem;
use Illuminate\Support\Facades\DB;

/**
 * Adds items to the learner's basket by their identity.
 *
 * The request always carries the whole set the learner chose, never a toggle,
 * so repeating it — a double press, a retry, a second tab — adds nothing new.
 * The unique constraint on the kind and identity makes that true even when two
 * requests arrive at the same moment.
 *
 * Only references are stored: no text, marks, statuses, ratings or sessions
 * are read or written here.
 */
final class AddBasketItems
{
    /** How many rows one insert carries; well under every driver's limit. */
    private const int CHUNK = 300;

    /**
     * @param  list<string>  $identities
     * @return array{added: list<string>, already: list<string>, total: int}
     */
    public function handle(FeedbackType $kind, array $identities): array
    {
        $identities = array_values(array_unique(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        )));

        if ($identities === []) {
            return ['added' => [], 'already' => [], 'total' => BasketItem::query()->count()];
        }

        return DB::transaction(function () use ($kind, $identities): array {
            /** @var list<string> $existing */
            $existing = BasketItem::query()
                ->where('kind', $kind->value)
                ->whereIn('item_identity', $identities)
                ->pluck('item_identity')
                ->all();

            $already = array_values(array_intersect($identities, $existing));
            $added = array_values(array_diff($identities, $existing));

            if ($added !== []) {
                $addedAt = now();

                foreach (array_chunk($added, self::CHUNK) as $chunk) {
                    BasketItem::query()->insertOrIgnore(array_map(
                        fn (string $identity): array => [
                            'kind' => $kind->value,
                            'item_identity' => $identity,
                            'added_at' => $addedAt,
                        ],
                        $chunk,
                    ));
                }
            }

            return [
                'added' => $added,
                'already' => $already,
                'total' => BasketItem::query()->count(),
            ];
        });
    }
}
