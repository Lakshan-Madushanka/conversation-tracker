<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * The items to take out of one review, named by kind and identity.
 *
 * The review comes from the address, so the body carries no `review` and no
 * `confirmed` answer — the rules they share with an add simply never arrive.
 */
final class RemoveCustomReviewItemsRequest extends AddCustomReviewItemsRequest {}
