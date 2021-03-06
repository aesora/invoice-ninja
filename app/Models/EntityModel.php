<?php namespace App\Models;

use Auth;
use Eloquent;
use Utils;

class EntityModel extends Eloquent
{
    public $timestamps = true;
    protected $hidden = ['id'];

    public static function createNew($parent = false)
    {
        $className = get_called_class();
        $entity = new $className();

        if ($parent) {
            $entity->user_id = $parent instanceof User ? $parent->id : $parent->user_id;
            $entity->account_id = $parent->account_id;
        } elseif (Auth::check()) {
            $entity->user_id = Auth::user()->id;
            $entity->account_id = Auth::user()->account_id;
        } else {
            Utils::fatalError();
        }

        $lastEntity = $className::withTrashed()->scope(false, $entity->account_id)->orderBy('public_id', 'DESC')->first();

        if ($lastEntity) {
            $entity->public_id = $lastEntity->public_id + 1;
        } else {
            $entity->public_id = 1;
        }

        return $entity;
    }

    public static function getPrivateId($publicId)
    {
        $className = get_called_class();

        return $className::scope($publicId)->pluck('id');
    }

    public function getActivityKey()
    {
        return '[' . $this->getEntityType().':'.$this->public_id.':'.$this->getDisplayName() . ']';
    }

    /*
    public function getEntityType()
    {
        return '';
    }

    public function getNmae()
    {
        return '';
    }
    */

    public function scopeScope($query, $publicId = false, $accountId = false)
    {
        if (!$accountId) {
            $accountId = Auth::user()->account_id;
        }

        $query->where($this->getTable() .'.account_id', '=', $accountId);

        if ($publicId) {
            if (is_array($publicId)) {
                $query->whereIn('public_id', $publicId);
            } else {
                $query->wherePublicId($publicId);
            }
        }

        return $query;
    }

    public function getName()
    {
        return $this->public_id;
    }

    public function getDisplayName()
    {
        return $this->getName();
    }

    // Remap ids to public_ids and show name
    public function toPublicArray()
    {
        $data = $this->toArray();

        foreach ($this->attributes as $key => $val) {
            if (strpos($key, '_id')) {
                list($field, $id) = explode('_', $key);
                if ($field == 'account') {
                    // do nothing
                } else {
                    $entity = @$this->$field;
                    if ($entity) {
                        $data["{$field}_name"] = $entity->getName();
                    }
                }
            }
        }

        $data = Utils::hideIds($data);

        return $data;
    }

}
