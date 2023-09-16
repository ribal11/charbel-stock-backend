<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_users extends Model
{
    use HasFactory;
    //Specify the table that is linked to this model
    protected $table = 'tbl_users';
    //specify the PK
    protected $primaryKey = 'user_code';
    //If your model's primary key is not an integer, you should define a protected $keyType property on your model. This property should have a value of string
    protected $keyType = 'string';
    // Indicates if the model's ID is auto-incrementing.
    public $incrementing = false;
    //Indicate to Eloquent not to include default columns updated_at and created_at in the
    //generated SQL statement
    public $timestamps = false;
}
