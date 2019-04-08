<?php

namespace {
    use Illuminate\Database\Eloquent\Model;

    if (!class_exists('__User')) {
        class __User extends Model
        {
            protected $table = 'users';
            protected $fillable = ['*'];
        }
    }

    if (!class_exists('__GlobalUser')) {
    class __GlobalUser extends Model
        {
            public $incrementing = false;
            public $timestamps = false;
        }
    }

    if (!class_exists('__Profile')) {
        class __Profile extends Model
        {
            public function user()
            {
                return $this->belongsTo(__User::class);
            }

            public function owner()
            {
                return $this->morphTo();
            }
        }
    }

    if (!class_exists('__Role')) {
        class __Role extends Model {
            protected $primaryKey = 'rid';

            public function users()
            {
                return $this->belongsToMany(__User::class, 'role_user', 'role_id', 'user_id');
            }

            public function managers()
            {
                return $this->morphToMany(__User::class, 'managable', null, null, 'user_id');
            }
        }
    }
}

namespace App\Models {
    use Illuminate\Database\Eloquent\Model;

    if (!class_exists('\App\Models\City')) {
        class City extends Model
        {
            protected $table = 'cities';
            protected $fillable = ['*'];
        }
    }
}