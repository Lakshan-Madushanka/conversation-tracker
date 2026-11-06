<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Basket\AddBasketItems;
use App\Actions\Basket\ClearBasket;
use App\Actions\Basket\PruneBasketItems;
use App\Actions\Basket\RemoveBasketItems;
use App\Http\Requests\AddBasketItemsRequest;
use App\Http\Requests\RemoveBasketItemsRequest;
use App\Http\Resources\BasketGroupResource;
use App\Queries\BasketContents;
use App\Queries\BasketCount;
use App\Support\BasketReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The custom-review basket: the items being gathered before a review is
 * saved, the page that lists them, and the small writes that add, remove or
 * clear them.
 *
 * The basket is only references. Nothing here reads or writes a mark, a
 * status, a rating, a schedule or a session, and its page is the one place
 * whose read repairs itself: references whose item no longer exists are
 * removed before the list is built.
 */
final class BasketController extends Controller
{
    public function index(
        Request $request,
        PruneBasketItems $pruneBasketItems,
        BasketContents $contents,
        BasketCount $count,
    ): Response {
        // A deleted item leaves no trace; an archived one stays but is marked
        // not available by the query.
        $pruneBasketItems->handle();

        $back = $request->query('from');

        return Inertia::render('basket/Index', [
            'groups' => array_map(
                fn (array $group): array => BasketGroupResource::make($group)->resolve(),
                $contents->groups(),
            ),
            'count' => $count->value(),
            'back_url' => BasketReturn::url(is_string($back) ? $back : null),
        ]);
    }

    /** Add the chosen items, repeating safely. */
    public function store(
        AddBasketItemsRequest $request,
        AddBasketItems $addBasketItems,
    ): JsonResponse {
        $result = $addBasketItems->handle($request->kind(), $request->identities());

        return response()->json([
            'added_count' => count($result['added']),
            'already_count' => count($result['already']),
            'added_identities' => $result['added'],
            'already_identities' => $result['already'],
            'total' => $result['total'],
        ]);
    }

    /** Remove the chosen items. Removing something already gone is calm. */
    public function destroy(
        RemoveBasketItemsRequest $request,
        RemoveBasketItems $removeBasketItems,
    ): JsonResponse {
        $result = $removeBasketItems->handle($request->kind(), $request->identities());

        return response()->json([
            'removed_count' => count($result['removed']),
            'removed_identities' => $result['removed'],
            'total' => $result['total'],
        ]);
    }

    /** Empty the basket. The page asks for confirmation before calling this. */
    public function clear(ClearBasket $clearBasket): JsonResponse
    {
        $removed = $clearBasket->handle();

        return response()->json([
            'cleared_count' => $removed,
            'total' => 0,
        ]);
    }
}
