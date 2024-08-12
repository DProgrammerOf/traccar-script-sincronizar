<?php

    namespace Models;

    class Diary extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'bem_diario';
        protected $connection = 'renan_tracker';
        protected $fillable = [ 'deviceid', 'imei', 'km_rodado', 'data' ];
        public $timestamps = false;

        public function getKm()
        {
            return $this->km_rodado;
        }
    }