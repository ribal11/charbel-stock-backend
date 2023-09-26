<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_invoicedetails extends Model
{
    use HasFactory;
    protected $table = 'tbl_invoicedetails';
    //specify the PK
    protected $primaryKey = 'ind_recid';

    public $incrementing = true;
    //Indicate to Eloquent not to include default columns updated_at and created_at in the
    //generated SQL statement
    public $timestamps = false;
}
