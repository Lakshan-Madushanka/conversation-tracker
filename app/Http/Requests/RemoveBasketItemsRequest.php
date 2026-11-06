<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * A set of items to take out of the basket. It names them the same way adding
 * does, so removing something that is not there is simply a no-op.
 */
final class RemoveBasketItemsRequest extends AddBasketItemsRequest {}
