<?php

declare(strict_types=1);

namespace Modules\Courses\Exceptions;

use Modules\Courses\Enums\ContentStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** A course content rule was broken; answered as 422 with the reason in Arabic. */
final class ContentRuleViolation extends HttpException
{
    private function __construct(string $message)
    {
        parent::__construct(422, $message);
    }

    public static function invalidStatusTransition(ContentStatus $from, ContentStatus $to): self
    {
        return new self("لا يمكن نقل الحالة من «{$from->label()}» إلى «{$to->label()}».");
    }

    public static function courseWithoutPublishedContent(): self
    {
        return new self('لا يمكن نشر دورة بلا درس منشور داخل قسم منشور.');
    }

    public static function subjectWithoutPublishedLessons(): self
    {
        return new self('لا يمكن نشر قسم بلا درس منشور.');
    }

    public static function alreadyPublished(): self
    {
        return new self('لا يمكن حذف محتوى سبق نشره؛ أخفِه أو أرشفه بدلاً من ذلك.');
    }

    public static function singleSubjectOutline(): self
    {
        return new self('هذه الدورة بمخطط دروس مباشر فلا تحتمل إلا قسماً واحداً.');
    }

    public static function reorderMismatch(): self
    {
        return new self('قائمة الترتيب يجب أن تضم كل العناصر الحالية مرة واحدة بالضبط.');
    }
}
