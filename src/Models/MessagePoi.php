<?php

    namespace Models;

    class MessagePoi extends \Illuminate\Database\Eloquent\Model
    {
        protected $connection = 'tracker';
        public $timestamps = false;
        protected $casts = [
            'attributes' => 'array'
        ];
        protected $fillable = [ 'poiId', 'deviceId', 'dateIn', 'dateOut', 'type', 'attributes' ];

        public function __construct($id = null, array $attributes = [])
        {
            parent::__construct($attributes);

            if ($id != null) {
                $this->setTable('message_poi_' . $id);
            }
        }
    }