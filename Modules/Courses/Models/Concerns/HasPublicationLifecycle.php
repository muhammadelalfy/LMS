<?php

declare(strict_types=1);

namespace Modules\Courses\Models\Concerns;

use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Exceptions\ContentRuleViolation;

/** draft → published → hidden → archived, with each model deciding what "publishable" means. */
trait HasPublicationLifecycle
{
    abstract public function assertPublishable(): void;

    public function transitionTo(ContentStatus $target): ContentStatus
    {
        $current = $this->status;
        if (! $current->canTransitionTo($target)) {
            throw ContentRuleViolation::invalidStatusTransition($current, $target);
        }
        if ($target === ContentStatus::Published) {
            $this->assertPublishable();
            $this->published_at ??= now();
        }
        $this->status = $target;

        return $current;
    }

    public function assertNeverPublished(): void
    {
        if ($this->published_at !== null) {
            throw ContentRuleViolation::alreadyPublished();
        }
    }
}
