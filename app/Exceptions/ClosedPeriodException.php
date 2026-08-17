<?php

namespace App\Exceptions;

use App\Models\AccountingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when something tries to post into a period that has been closed.
 *
 * Extends RuntimeException on purpose: the document controllers already treat
 * a RuntimeException from the posting service as "this could not be booked,
 * tell the user why and roll the rest back", which is exactly the handling a
 * closed period needs. Being its own class lets a caller that wants to say
 * something more specific recognise it.
 */
class ClosedPeriodException extends RuntimeException
{
    public function __construct(public readonly string $date, public readonly AccountingPeriod $period)
    {
        parent::__construct(sprintf(
            'الفترة المحاسبية "%s" مقفلة، ولا يمكن ترحيل قيد بتاريخ %s. أعد فتح الفترة أو استخدم تاريخاً داخل فترة مفتوحة.',
            $period->name,
            $date
        ));
    }

    /**
     * Answers as a refusal rather than a crash.
     *
     * Most callers of the posting service already catch a RuntimeException and
     * explain it, but not all of them: a stock adjustment made on a day inside
     * a closed period travels up through the inventory service, and without
     * this it would reach the user as a 500 with no hint of what was wrong.
     * Laravel calls this method when an exception defines it, so every path is
     * covered whether or not its controller thought about periods.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'data' => null,
            'closed_period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'start_date' => $this->period->start_date?->toDateString(),
                'end_date' => $this->period->end_date?->toDateString(),
            ],
        ], 422);
    }
}
