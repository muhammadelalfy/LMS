<?php

namespace Modules\Calls\Models;

use Illuminate\Database\Eloquent\Model;

/** How a group meets online on one day: in the app, or by a Meet or Zoom link. */
class OnlineSession extends Model
{
    protected $fillable = ['group_id', 'day', 'provider', 'link', 'created_by'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    public function present(): array
    {
        return ['group_id' => $this->group_id, 'day' => $this->day->toDateString(), 'provider' => $this->provider, 'link' => $this->link];
    }
}
