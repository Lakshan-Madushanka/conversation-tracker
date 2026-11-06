<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reviews\StartReview;
use App\Enums\ReviewCollection;
use App\Http\Requests\CustomReviewRequest;
use App\Http\Resources\CustomReviewPreviewResource;
use App\Models\Review;
use App\Queries\ReviewTopics;
use App\Queries\TodaysReview;
use App\Support\ReviewSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A custom review: the page that asks what to look at, the live count of what
 * matches, and the sitting that starts from those same choices.
 *
 * The choices travel in the address and are never saved. The count is the
 * sitting — the preview and the review both come from the one suggestion the
 * daily review uses, narrowed by `App\Support\ReviewFilters`, so the mix, the
 * estimate, the allocation and the order are shared and cannot disagree. What
 * starts is an ordinary review with a fixed list, marked custom so it is never
 * mistaken for the day's suggestion, and it follows the same stepper, early
 * finish, undo and rating rules as any other.
 */
class CustomReviewController extends Controller
{
    /**
     * The page, opened with the address's choices already applied and already
     * counted, so there is no blank moment before the first number.
     */
    public function index(
        CustomReviewRequest $request,
        ReviewTopics $topics,
        TodaysReview $todaysReview,
    ): Response {
        $filters = $request->filters();

        return Inertia::render('review/Custom', [
            // The choices exactly as the server understood them, so the page and the
            // address can never disagree about what is set.
            'choices' => $this->choices($request),
            'topics' => $topics->list($filters),
            'collections' => array_map(fn (ReviewCollection $collection): array => [
                'value' => $collection->value,
                'label' => $collection->label(),
            ], ReviewCollection::ordered()),
            'sizes' => ReviewSettings::CUSTOM_SIZES,
            'dailySize' => ReviewSettings::SIZE,
            'preview' => $this->previewPayload($request, $todaysReview),
        ]);
    }

    /**
     * Start the sitting the choices describe. Nothing reviewable under them
     * goes back to the page, where the count already says so, rather than
     * opening an empty sitting.
     */
    public function store(
        CustomReviewRequest $request,
        StartReview $startReview,
    ): RedirectResponse {
        $review = $startReview->handle(
            request: $request,
            filters: $request->filters(),
            size: $request->size(),
        );

        if (! $review instanceof Review) {
            return to_route('review.custom', $this->address($request));
        }

        return to_route('review.session', ['review' => $review->id]);
    }

    /**
     * The address for these choices. Defaults are left out, so an untouched
     * page keeps a short address, exactly as the page itself writes it.
     *
     * @return array<string, string>
     */
    private function address(CustomReviewRequest $request): array
    {
        $query = $request->filters()->toQuery();
        $size = $request->size();

        if ($size !== ReviewSettings::SIZE) {
            $query['size'] = (string) $size;
        }

        return $query;
    }

    /**
     * The choices as the server understood them, so the page draws exactly
     * what the count was worked out from and never has to guess what an
     * unknown value became.
     *
     * @return array{
     *     topics: list<int>,
     *     collections: list<string>,
     *     starred: bool,
     *     learned: bool,
     *     size: int
     * }
     */
    private function choices(CustomReviewRequest $request): array
    {
        $filters = $request->filters();

        return [
            'topics' => $filters->topicIds,
            'collections' => array_map(
                fn (ReviewCollection $collection): string => $collection->value,
                $filters->collections,
            ),
            'starred' => $filters->starredOnly,
            'learned' => $filters->includeLearned,
            'size' => $request->size(),
        ];
    }

    /**
     * @return array{
     *     count: int,
     *     size: int,
     *     minutes: int,
     *     mix: string,
     *     comfortable: bool,
     *     kinds: int
     * }
     */
    private function previewPayload(CustomReviewRequest $request, TodaysReview $todaysReview): array
    {
        $suggestion = $todaysReview->suggest(
            size: $request->size(),
            request: $request,
            filters: $request->filters(),
        );

        /** @var array{count: int, size: int, minutes: int, mix: string, comfortable: bool, kinds: int} $payload */
        $payload = (new CustomReviewPreviewResource($suggestion, $request->size()))->resolve();

        return $payload;
    }
}
