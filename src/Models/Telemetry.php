<?php

    namespace Models;

    class Telemetry extends \Illuminate\Database\Eloquent\Model
    {
        protected $connection = 'tracker';
        protected $fillable = [ 'date', 'general', 'behavior' ];
        protected $casts = [ 'general' => 'array' ];
        public $timestamps = false;

        public function __construct($id = null, array $attributes = [])
        {
            parent::__construct($attributes);

            if ($id != null) {
                $this->setTable('telemetria_cliente_' . $id );
            }
        }
    }