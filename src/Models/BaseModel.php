<?php

namespace Ongudidan\FortuneCore\Models;

use Illuminate\Database\Eloquent\Model;
use Ongudidan\FortuneCore\Traits\ConfiguresTableNames;
use Ongudidan\FortuneCore\Traits\ResolvesModels;

abstract class BaseModel extends Model
{
    use ConfiguresTableNames, ResolvesModels;

    protected $guarded = ['id'];
}
