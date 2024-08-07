<?php

    namespace Models;

    class Gprmc extends \Illuminate\Database\Eloquent\Model
    {
        protected $connection = 'tracker';

        public function __construct($imei = null, array $attributes = [])
        {
            parent::__construct($attributes);

            if ($imei != null ) {
                $this->setTable('gprmc_' . $imei );
            }
        }
    }