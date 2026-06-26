<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberSave extends Model
{
    protected $fillable = ['member_id', 'type', 'item_id'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
