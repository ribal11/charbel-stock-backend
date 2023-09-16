<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_items extends Model
{
    use HasFactory;
    //Specify the table that is linked to this model
    protected $table = 'tbl_items';
    //specify the PK
    protected $primaryKey = 'stk_recid';

    public $incrementing = true;
    //Indicate to Eloquent not to include default columns updated_at and created_at in the
    //generated SQL statement
    public $timestamps = false;
}
